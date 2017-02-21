<?php

namespace App\Console\Commands\Crawl;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrawlCylanceDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:cylancedevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl Cylance web console and parse out device list';

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
        /*******************************
         * [1] Get all Cylance devices *
         *******************************/

        Log::info(PHP_EOL.PHP_EOL.'*************************************'.PHP_EOL.'* Starting Cylance devices crawler! *'.PHP_EOL.'*************************************');

        $username = getenv('CYLANCE_USERNAME');
        $password = getenv('CYLANCE_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup file to hold cookie
        $cookiejar = storage_path('app/cookies/cylancecookie.txt');

        // create crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // set login URL
        $url = 'https:/'.'/login.cylance.com/Login';

        // hit login page and capture response
        $response = $crawler->get($url);

        Log::info('logging in to: '.$url);

        // If we DONT get the dashboard then we need to try and login
        $regex = '/<title>CylancePROTECT \| Dashboard<\/title>/';
        $tries = 0;
        while (!preg_match($regex, $response, $hits) && $tries <= 3) {
            $regex = '/RequestVerificationToken" type="hidden" value="(.+?)"/';

            // if we find the RequestVerificationToken then assign it to $csrftoken
            if (preg_match($regex, $response, $hits)) {
                $csrftoken = $hits[1];
            } else {
                // otherwise, dump response and die
                file_put_contents($response_path.'cylance_login.dump', $response);

                Log::error('Error: could not extract CSRF token from response');
                die('Error: could not extract CSRF token from response!'.PHP_EOL);
            }

            // use csrftoken and credentials to create post data
            $post = [
                '__RequestVerificationToken'   => $csrftoken,
                'Email'                        => $username,
                'Password'                     => $password,
            ];

            // try and post login data to the website
            $response = $crawler->post($url, $url, $this->postArrayToString($post));

            // increment tries and set regex back to Dashboard title
            $tries++;
            $regex = '/<title>CylancePROTECT \| Dashboard<\/title>/';
        }
        // once out of the login loop, if $tries is >= to 3 then we couldn't get logged in
        if ($tries > 3) {
            Log::error('Error: could not post successful login within 3 attempts');
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // dump dashboard html to a file
        file_put_contents($response_path.'cylance_dashboard.dump', $response);

        // look for javascript token
        $regex = '/var\s+token\s+=\s+"(.+)"/';

        // if we find the javascript token then set it to $token
        if (preg_match($regex, $response, $hits)) {
            $token = $hits[1];
        } else {
            // otherwise die
            Log::error('Error: could not get javascript token');
            die('Error: could not get javascript token crap'.PHP_EOL);
        }

        // use javascript token to setup necessary HTTP headers
        $headers = [
            'X-Request-Verification-Token: '.$token,
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // point url to the devices list API endpoint
        $url = 'https:/'.'/protect.cylance.com/Grids/DevicesList_Ajax';

        // setup collection array and variables for paging
        $collection = [];
        $i = 0;
        $page = 1;
        $page_size = 1000;

        // setup necessary post data
        $post = [
            'sort'      => 'Name-asc',
            'page'      => $page,
            'pageSize'  => $page_size,
            'group'     => '',
            'aggregate' => '',
            'filter'    => '',
        ];

        // start paging
        Log::info('starting page scrape loop');
        do {
            Log::info('scraping loop for page '.$page);

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, '', $this->postArrayToString($post));

            // dump raw response to devices.dump.* file where * is the page number
            file_put_contents($response_path.'devices.dump.'.$page, $response);

            // json decode the response
            $devices = \Metaclassing\Utility::decodeJson($response);

            // save this pages response array to our collection
            $collection[] = $devices;

            // set count to the total number of devices returned with each response
            $count = $devices['Total'];

            Log::info('scrape for page '.$page.' complete - got '.count($devices['Data']).' devices');

            $i += $page_size;  // increment i by page_size
            $page++;           // increment the page number

            // wait a second before hammering on their webserver again
            sleep(1);
        } while ($i < $count);

        // instantiate cylance device list
        $cylance_devices = [];

        // first level is simple sequencial array of 1,2,3
        foreach ($collection as $response) {
            // next level down is associative, the KEY we care about is 'Data'
            $results = $response['Data'];
            foreach ($results as $device) {
                // this is confusing logic.
                $cylance_devices[] = $device;
            }
        }

        Log::info('devices successfully collected: '.count($cylance_devices));

        // Now we have a simple array [1,2,3] of all the device records,
        // each device record is a key=>value pair collection / assoc array
        file_put_contents(storage_path('app/collections/devices.json'), \Metaclassing\Utility::encodeJson($cylance_devices));
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

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }
}
