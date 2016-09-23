<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlCylanceThreats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:cylancethreats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl Cylance web console and parse out threat list';

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
        $username = getenv('CYLANCE_USERNAME');
        $password = getenv('CYLANCE_PASSWORD');

        $response_path = storage_path('logs/responses/');

        // setup file to hold cookie
        $cookiejar = storage_path('logs/cookies/cylance_cookie.txt');
        echo 'storing cookies at '.$cookiejar.PHP_EOL;

        // create crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // set login URL
        $url = 'https:/'.'/login.cylance.com/Login';

        // hit login page and capture response
        $response = $crawler->get($url);

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
                file_put_contents($response_path.'cylancethreats_error.dump', $response);
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
            // var_dump($response);
            // add error handling logic if login STILL failed after attempt.
            // while !preg_match -> and tries < 3 or whatever
        }
        // once out of the login loop, if $tries is >= to 3 then we couldn't get logged in
        if ($tries > 3) {
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // dump dashboard html to a file
        file_put_contents($response_path.'cylance_dashboard.dump', $response);

        // look for javascript token
        $regex = '/var\s+token\s+=\s+"(.+)"/';

        // if we find the javascript token then set it to $token
        if (preg_match($regex, $response, $hits)) {
            $token = $hits[1];
            echo 'found javascript token: '.$token.PHP_EOL;
        } else {
            // otherwise die
            die('Error: could not get javascript token crap'.PHP_EOL);
        }

        // use javascript token to setup necessary HTTP headers
        $headers = [
            'X-Request-Verification-Token: '.$token,
            'X-Requested-With: XMLHttpRequest',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // change url to point to the threat list
        $url = 'https:/'.'/my-vs0.cylance.com/Threats/ListThreatsOverview';

        // setup necessary post data
        $post = [
            'sort'      => 'ActiveInDevices-desc',
            'page'      => '1',
            'pageSize'  => '50',
            'group'     => '',
            'aggregate' => '',
            'filter'    => '',
        ];

        // setup collection array and variables for paging
        $collection = [];
        $i = 0;
        $page = 1;

        echo 'starting page scrape loop'.PHP_EOL;
        do {
            echo 'scraping loop for page '.$page.PHP_EOL;

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, 'https:/'.'/my-vs0.cylance.com/Threats', $this->postArrayToString($post));

            // dump raw response to threats.dump.* file where * is the page number
            file_put_contents($response_path.'threats.dump.'.$page, $response);

            // json decode the response
            $threats = json_decode($response, true);

            // save this pages response array to our collection
            $collection[] = $threats;

            // set count to the total number of devices returned with each response.
            // this should not change from response to response
            $count = $threats['Total'];

            echo 'scrape for page '.$page.' complete - got '.count($threats).' threat records'.PHP_EOL;

            $i += 50;   // Increase i by PAGESIZE!
            $page++;    // Increase the page number

            sleep(1);   // wait a second before hammering on their webserver again
        } while ($i < $count);

        $cylance_threats = [];

        // first level is simple sequencail array of 1,2,3
        foreach ($collection as $response) {
            // next level down is associative, the KEY we care about is 'Data'
            $results = $response['Data'];
            foreach ($results as $threat) {
                // this is confusing logic.
                $cylance_threats[] = $threat;
            }
        }

        // Now we ahve a simple array [1,2,3] of all the threat records,
        // each threat record is a key=>value pair collection / assoc array
        //\Metaclassing\Utility::dumper($threats);
        file_put_contents(storage_path('logs/collections/threats.json'), \Metaclassing\Utility::encodeJson($cylance_threats));
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
