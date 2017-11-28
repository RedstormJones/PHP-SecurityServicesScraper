<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\IronPort\IronPortSpamEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSpamEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:spamemail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new IronPort spam email records';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /*
         * [1] Get spam email
         */

        Log::info(PHP_EOL.PHP_EOL.'********************************'.PHP_EOL.'* Starting spam email crawler! *'.PHP_EOL.'********************************');

        $username = getenv('IRONPORT_USERNAME');
        $password = getenv('IRONPORT_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup cookiejar file
        $cookiejar = storage_path('app/cookies/ironport_cookie.txt');

        // instantiate crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // set url
        $url = getenv('IRONPORT_SMA');

        // hit webpage
        $response = $crawler->get($url);
        file_put_contents($response_path.'spam_login.page', $response);

        // set regex string to dashboard page <title> element
        $regex = '/(<title>.*Centralized Services &gt; System Status <\/title>)/';
        $tries = 0;

        // while NOT at the dashboard page
        while (!preg_match($regex, $response, $hits) && $tries <= 3) {
            // set login URL and post data
            $url = getenv('IRONPORT_SMA').'/login';

            $post = [
                'action'        => 'Login',
                'referrer'      => '',
                'screen'        => 'login',
                'username'      => $username,
                'password'      => $password,
            ];

            // try to login
            $response = $crawler->post($url, $url, $this->postArrayToString($post));
            file_put_contents($response_path.'spam_login.response', $response);

            // increment tries and set regex back to dashboard <title>
            $tries++;
            $regex = '/(<title>.*Centralized Services &gt; System Status <\/title>)/';
        }
        // once out of the login loop, if tries is > 3 then we didn't login so die
        if ($tries > 3) {
            Log::error('could not post successful login within 3 attempts');
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // dump dashboard to file
        file_put_contents($response_path.'ironport_dashboard.dump', $response);

        // now that we're in head over to the local quarantines
        $url = getenv('IRONPORT_SMA').'/monitor_email_quarantine/local_quarantines';

        // capture response and try to extract CSRF token (it might be a new one)
        $response = $crawler->get($url);
        $regex = "/CSRFKey = '(.+)'/";
        if (preg_match($regex, $response, $hits)) {
            $csrftoken = $hits[1];
        } else {
            // if no CSRFToken, pop smoke
            Log::error('could not get CSRF token from quarantines page');
            die('Error: could not get CSRF token from quarantines page'.PHP_EOL);
        }

        // setup url and referer to go to the Centralized Policy spam quarantine
        $url = getenv('IRONPORT_SMA').'/monitor_email_quarantine/local_quarantines_dosearch?';
        $referer = getenv('IRONPORT_SMA').'/monitor_email_quarantine/local_quarantines';
        $refparams = [
            'CSRFKey'       => $csrftoken,
            'clear'         => 'true',
            'name'          => 'Policy',
            'mquar_sort'    => 'time_desc',
        ];

        // append GET parameters to url and send request to web server
        $spam_url = $url.$this->postArrayToString($refparams);
        $response = $crawler->get($spam_url, $referer);
        file_put_contents($response_path.'ironport_localquarantines.dump', $response);

        // find time_stamp value in response
        $regex = "/time_stamp=(\d+.\d+)/";
        if (preg_match($regex, $response, $hits)) {
            $time_stamp = $hits[1];
        } else {
            // if no time_stamp value then no working request so die
            Log::error('could not get time_stamp');
            die('Error: could not get time_stamp'.PHP_EOL);
        }

        // create necessary HTTP headers and configure curl with them
        $headers = [
            'X-Requested-With: XMLHttpRequest',
        ];

        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // setup url spam search url
        $url = getenv('IRONPORT_SMA').'/monitor_email_quarantine/local_quarantines_dosearch?';

        $collection = [];
        $page = 1;
        $i = 0;
        $pagesize = 1000;

        do {
            // setup GET parameters and append to spam search url
            $getmessages = [
                'action'     => 'GetMessages',
                'CSRFKey'    => $csrftoken,
                'name'       => 'Policy',
                'key'        => 'time_added',
                'dir'        => 'desc',
                'time_stamp' => $time_stamp,
                'pg'         => $page,
                'pageSize'   => $pagesize,
            ];

            $geturl = $url.$this->postArrayToString($getmessages);

            // capture reponse and dump to file
            $response = $crawler->get($geturl, $referer);
            file_put_contents($response_path.'spam.dump.'.$page, $response);

            // JSON decode the response and add it to the spam collection
            $spam = \Metaclassing\Utility::decodeJson($response);
            $collection[] = $spam;

            Log::info('spam email scrape for page '.$page.' complete - got '.count($spam['search_result']).' spam records');

            // set total to total number of messages
            $total = $spam['num_msgs'];

            $i += $pagesize;    // increment by number of records per page
            $page++;            // increment to next page

            // sleep for 1 second before hammering on IronPort again
            sleep(1);
        } while ($i < $total);

        $spam_array = [];

        // first level is simple sequencail array of 1,2,3
        foreach ($collection as $response) {
            // next level down is associative, the KEY we care about is 'Data'
            $results = $response['search_result'];
            foreach ($results as $spammer) {
                // this is confusing logic.
                $spam_array[] = $spammer;
            }
        }

        $spam_emails = [];
        $time_added_regex = '/(\d{2} \w{3} \d{4} \d{1,2}:\d{2})/';

        foreach ($spam_array as $spam) {
            // get the time_added datetime and format it correctly - there's probably a better way to do this
            preg_match($time_added_regex, $spam['time_added'], $hits);
            $time_added = Carbon::createFromFormat('d M Y H:i', $hits[1])->toDateTimeString();
            $time_added_pieces = explode(' ', $time_added);
            $time_added = $time_added_pieces[0].'T'.$time_added_pieces[1];

            $spam_emails[] = [
                'time_added'            => $time_added,
                'is_copy'               => $spam['is_copy'],
                'encrypt_on_release'    => $spam['encrypt_on_release'],
                'recipients'            => $spam['recipients'],
                'quarantine_names'      => $spam['quarantine_names'],
                'esa_id'                => $spam['esa_id'],
                'in_other_quarantines'  => $spam['in_other_quarantines'],
                'subject'               => $spam['subject'],
                'mid'                   => $spam['mid'],
                'reason'                => $spam['reason'],
                'expiration'            => $spam['expiration'],
                'size'                  => $spam['size'],
                'sender'                => $spam['sender'],
                'dlp_violated'          => $spam['dlp_violated'],
                'other_quarantines'     => $spam['other_quarantines'],
                'other'                 => $spam['other'],
                'quarantines'           => $spam['quarantines'],
                'tracking'              => $spam['tracking'],
            ];
        }

        file_put_contents(storage_path('app/collections/spam.json'), \Metaclassing\Utility::encodeJson($spam_emails));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($spam_emails as $spam) {
            $result = $producer->send([
                [
                    'topic' => 'ironport_spam_email',
                    'value' => \Metaclassing\Utility::encodeJson($spam),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$spam['sender']);
            }
        }

        /*
        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($spam_emails as $spam) {
            $url = 'http://10.243.32.36:9200/ironport_spam/ironport_spam/'.$spam['mid'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $spam,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('IronPort spam email was successfully inserted into ES: '.$spam['mid']);
            } else {
                Log::error('Something went wrong upserting IronPort spam email: '.$spam['mid']);
                die('Something went wrong upserting IronPort spam email: '.$spam['mid'].PHP_EOL);
            }
        }
        */

        Log::info('* Completed IronPort spam emails! *');
    }

    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];

        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.$value;
        }

        $poststring = implode('&', $postarray);

        return $poststring;
    }

    /**
     * Function to process softdeletes for spam email.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subMonths(3);
        Log::info('spam delete date: '.$delete_date);

        $spam_emails = IronPortSpamEmail::all();

        foreach ($spam_emails as $spam) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $spam->updated_at);

            if ($updated_at->lt($delete_date)) {
                Log::info('deleting spam record: '.$spam->id);
                $spam->delete();
            }
        }
    }
}
