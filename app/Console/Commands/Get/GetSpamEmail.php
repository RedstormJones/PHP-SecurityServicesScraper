<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

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
        $pagesize = 2000;

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
            $json_response = $crawler->get($geturl, $referer);
            file_put_contents($response_path.'spam.dump.'.$page, $json_response);

            // JSON decode the response and add it to the spam collection
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $collection[] = $response['search_result'];

            Log::info('spam email scrape for page '.$page.' complete - got '.count($response['search_result']).' spam records');

            // set total to total number of messages
            $total = $response['num_msgs'];

            $i += $pagesize;    // increment by number of records per page
            $page++;            // increment to next page

            // sleep for 1 second before hammering on IronPort again
            sleep(1);
        } while ($i < $total);

        // collapse collection into a simple array ( ex. [[1,2,3],[4,5,6],[7,8]] ==> [1,2,3,4,5,6,7,8] )
        $raw_spams = array_collapse($collection);

        $spam_emails = [];
        $time_added_regex = '/(\d{2} \w{3} \d{4} \d{1,2}:\d{2})/';

        foreach ($raw_spams as $spam) {
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
            // add upsert datetime
            $spam['upsert_date'] = Carbon::now()->toAtomString();

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
}
