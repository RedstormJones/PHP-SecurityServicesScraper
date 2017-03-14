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

        // hit webpage and try to capture CSRF token, otherwise die
        $response = $crawler->get($url);
        file_put_contents($response_path.'ironport_login.spam.dump', $response);

        // set regex string to dashboard page <title> element
        $regex = '/(<title>        Cisco         Content Security Management Appliance   M804 \(dh1146-sma1\.iphmx\.com\) -         Centralized Services &gt; System Status <\/title>)/';
        $tries = 0;

        // while NOT at the dashboard page
        while (!preg_match($regex, $response, $hits) && $tries <= 3) {
            // find CSRFKey value
            $regex = '/CSRFKey=([\w-]+)/';

            if (preg_match($regex, $response, $hits)) {
                $csrftoken = $hits[1];
            } else {
                Log::error('could not get CSRF token');
                die('Error: could not get CSRF token'.PHP_EOL);
            }

            // set login URL and post data
            $url = getenv('IRONPORT_SMA').'/login';

            $post = [
                'action'    => 'Login',
                'referrer'  => getenv('IRONPORT_SMA').'/default',
                'screen'    => 'login',
                'username'  => $username,
                'password'  => $password,
            ];

            // try to login
            $response = $crawler->post($url, $url, $this->postArrayToString($post));

            // increment tries and set regex back to dashboard <title>
            $tries++;
            $regex = '/(<title>        Cisco         Content Security Management Appliance   M804 \(dh1146-sma1\.iphmx\.com\) -         Centralized Services &gt; System Status <\/title>)/';
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
            Log::error('could not get CSRF token');
            die('Error: could not get CSRF Token'.PHP_EOL);
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
        $pagesize = 100;

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

            // set count to total number of messages
            $count = $spam['num_msgs'];

            $i += $pagesize;   // increment by number of records per page
            $page++;    // increment to next page

            // sleep for 1 second before hammering on IronPort again
            sleep(1);
        } while ($i < $count);

        $spam_emails = [];

        // first level is simple sequencail array of 1,2,3
        foreach ($collection as $response) {
            // next level down is associative, the KEY we care about is 'Data'
            $results = $response['search_result'];
            foreach ($results as $spammer) {
                // this is confusing logic.
                $spam_emails[] = $spammer;
            }
        }

        // Now we ahve a simple array [1,2,3] of all the threat records,
        // each threat record is a key=>value pair collection / assoc array
        //\Metaclassing\Utility::dumper($spam_emails);
        file_put_contents(storage_path('app/collections/spam.json'), \Metaclassing\Utility::encodeJson($spam_emails));

        /*
         * [2] Process spam emails into database
         */

        Log::info(PHP_EOL.'***********************************'.PHP_EOL.'* Starting spam email processing! *'.PHP_EOL.'***********************************');

        $contents = file_get_contents(storage_path('app/collections/spam.json'));
        $spam_emails = \Metaclassing\Utility::decodeJson($contents);

        foreach ($spam_emails as $spam) {
            $reasons = '';
            // cycle through reasons
            foreach ($spam['reason'] as $reason) {
                // grab policy array and convert to a ';' separated string
                $policy_arr = $reason[1];
                $reason_str = implode('; ', $policy_arr);
                // appeand to reasons string
                $reasons .= $reason_str.'; ';
            }

            // strip crap from time_added
            $timeadded = substr($spam['time_added'], 0, -13);

            // we need to check that timeadded is formatted correctly and, if not,
            // append 0's for either the seconds or for both the seconds and minutes
            if (!strstr($timeadded, ':')) {
                if (strlen($timeadded) == 11) {
                    $timeadded .= '00:00';
                } else {
                    $timeadded .= ':00';
                }
            } else {
                $timeadded = str_pad($timeadded, 17, '0');
            }

            // now we can use timeadded to create a datetime object
            $date = \DateTime::createFromFormat('d M Y H:i', $timeadded);
            $datetime = $date->format('Y-m-d H:i');

            // convert quarantine names and recipients arrays to strings
            $quarantines = implode('; ', $spam['quarantine_names']);
            $recipients = implode('; ', $spam['recipients']);

            $exists = IronPortSpamEmail::where('mid', $spam['mid'])->value('id');

            if ($exists) {
                $spam = IronPortSpamEmail::find($exists);

                $spam->update([
                    'mid'                 => $spam['mid'],
                    'subject'             => $spam['subject'],
                    'size'                => $spam['size'],
                    'quarantine_names'    => $quarantines,
                    'time_added'          => $datetime,
                    'reason'              => $reasons,
                    'recipients'          => $recipients,
                    'sender'              => $spam['sender'],
                    'esa_id'              => $spam['esa_id'],
                    'data'                => \Metaclassing\Utility::encodeJson($spam),
                ]);

                $spam->save();

                // touch spam record to update 'updated_at' timestamp in case nothing was changed
                $spam->touch();

                Log::info('updated spam record for: '.$spam['sender']);
            } else {
                Log::info('creating spam record for sender: '.$spam['sender']);

                $new_spam = new IronPortSpamEmail();

                $new_spam->mid = $spam['mid'];
                $new_spam->subject = $spam['subject'];
                $new_spam->size = $spam['size'];
                $new_spam->quarantine_names = $quarantines;
                $new_spam->time_added = $datetime;
                $new_spam->reason = $reasons;
                $new_spam->recipients = $recipients;
                $new_spam->sender = $spam['sender'];
                $new_spam->esa_id = $spam['esa_id'];
                $new_spam->data = \Metaclassing\Utility::encodeJson($spam);

                $new_spam->save();
            }
        }

        /*
         * 2017-01-17 - Not deleting spam email records at this time to allow for trending visuals
         */
        $this->processDeletes();

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
        $delete_date = Carbon::now()->subMonths(6);
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
