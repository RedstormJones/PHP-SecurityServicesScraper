<?php

namespace App\Console\Commands\Crawl;

require_once app_path('Console/Crawler/Crawler.php');

use App\Cylance\CylanceThreat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrawlCylanceDevicesByThreat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:cylancedevicesbythreat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get information on each device corresponding to every Cylance threat';

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
        Log::info(PHP_EOL.PHP_EOL.'***********************************************'.PHP_EOL.'* Starting Cylance devices by threat crawler! *'.PHP_EOL.'***********************************************');

        $username = getenv('CYLANCE_USERNAME');
        $password = getenv('CYLANCE_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup file to hold cookie
        $cookiejar = storage_path('app/cookies/cylance_cookie.txt');

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
                Log::info('request verification token: '.$csrftoken);
            } else {
                // otherwise, dump response and die
                file_put_contents($response_path.'cylancethreats_error.dump', $response);

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

        // once you're logged in hit the Threats page
        $url = 'https:/'.'/protect.cylance.com/Threats';
        $response = $crawler->get($url);

        // look for javascript token on Threats page
        $regex = '/var\s+token\s+=\s+"(.+)"/';

        // if we find the javascript token then set it to $token
        if (preg_match($regex, $response, $hits)) {
            $token = $hits[1];
            Log::info('threats token: '.$token);
        } else {
            // otherwise die
            Log::error('Error: could not get javascript token');
            die('Error: could not get javascript token crap'.PHP_EOL);
        }

        // use javascript token to setup necessary HTTP headers
        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Request-Verification-Token: '.$token,
            'X-Requested-With: XMLHttpRequest',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // holds the collections returned for each particular threat id
        $threat_collection = [];

        $threat_ids = CylanceThreat::pluck('threat_id');
        $threat_ids_count = $threat_ids->count();
        Log::info('threat id count from securitymetrics db: '.$threat_ids_count);

        // cycle through each threat id and query for the device
        foreach ($threat_ids as $id)
        {

            // query for active, blocked, allowed and suspicious
            for ($t = 0; $t < 4; $t++)
            {
                //$threat_collection[] = $this->queryForDetails($crawler, $id, $t);
                
                if ($t == 0) {
                    Log::info('querying for devices with active threat: '.$id);
                    $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesActive?filehashId='.$id;
                } elseif ($t == 1) {
                    Log::info('querying for devices with blocked threat: '.$id);
                    $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesBlocked?filehashId='.$id;
                } elseif ($t == 2) {
                    Log::info('querying for devices with allowed threat: '.$id);
                    $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesAllowed?filehashId='.$id;
                } elseif ($t == 3) {
                    Log::info('querying for devices with suspicious threat: '.$id);
                    $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesSuspicious?filehashId='.$id;
                } else {
                    Log::error('something went wrong');
                    die();
                }

                // setup collection array and variables for paging
                $collection = [];
                $i = 0;
                $page = 1;
                $page_size = 1000;

                // setup necessary post data
                $post = [
                    'sort'      => '',
                    'page'      => $page,
                    'pageSize'  => $page_size,
                    'group'     => '',
                    'aggregate' => '',
                    'filter'    => '',
                ];

                do {
                    // set the post page to our current page number
                    $post['page'] = $page;

                    // post data to webpage and capture response, which is hopefully a list of devices
                    $response = $crawler->post($url, '', $this->postArrayToString($post));

                    // dump raw response to threats.dump.* file where * is the page number
                    //file_put_contents(storage_path('app/responses/threats.dump.'.$page), $response);

                    // json decode the response
                    $threats = json_decode($response, true);

                    // save this pages response array to our collection
                    $collection[] = $threats;

                    // set count to the total number of devices returned with each response.
                    // this should not change from response to response
                    $count = $threats['Total'];

                    Log::info('count of devices found: '.count($threats['Data']));

                    $i += $page_size;   // increment i by page_size
                    $page++;            // increment the page number

                    //sleep(1);   // wait a second before hammering on their webserver again
                } while ($i < $count);

                // first level is simple sequencail array of 1,2,3
                foreach ($collection as $response) {
                    // next level down is associative, the KEY we care about is 'Data'
                    $results = $response['Data'];
                    if (count($results) > 0)
                    {
                        foreach ($results as $threat) {
                            // this is confusing logic.
                            $threat_collection[] = $threat;
                        }
                    }
                }

            }

            $threat_ids_count--;
            Log::info('count of threat ids left: '.$threat_ids_count);
        }

        Log::info('total collection count: '.count($threat_collection));
        file_put_contents(storage_path('app/collections/devices_by_threat.json'), \Metaclassing\Utility::encodeJson($threat_collection));

        Log::info('* Cylance devices by threat completed! *'.PHP_EOL);
    }

    /**
     * Function to query for the device details for each known threat
     *
     * @return mixed
     */
    public function queryForDetails($crawler, $id, $t)
    {
        if ($t == 0) {
            Log::info('querying for devices with active threat: '.$id);
            $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesActive?filehadhId='.$id;
        } elseif ($t == 1) {
            Log::info('querying for devices with blocked threat: '.$id);
            $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesBlocked?filehadhId='.$id;
        } elseif ($t == 2) {
            Log::info('querying for devices with allowed threat: '.$id);
            $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesAllowed?filehadhId='.$id;
        } elseif ($t == 3) {
            Log::info('querying for devices with suspicious threat: '.$id);
            $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesSuspicious?filehadhId='.$id;
        } else {
            Log::error('something went wrong');
            die();
        }

        // setup collection array and variables for paging
        $collection = [];
        $i = 0;
        $page = 1;
        $page_size = 1000;

        // setup necessary post data
        $post = [
            'sort'      => '',
            'page'      => $page,
            'pageSize'  => $page_size,
            'group'     => '',
            'aggregate' => '',
            'filter'    => '',
        ];

        do {
            Log::info('scraping loop for page '.$page);

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, '', $this->postArrayToString($post));

            // dump raw response to threats.dump.* file where * is the page number
            file_put_contents(storage_path('app/responses/threats.dump.'.$page), $response);

            // json decode the response
            $threats = json_decode($response, true);

            // save this pages response array to our collection
            $collection[] = $threats;

            // set count to the total number of devices returned with each response.
            // this should not change from response to response
            $count = $threats['Total'];

            Log::info('scrape for page '.$page.' complete - got '.count($threats['Data']).' threats');

            $i += $page_size;   // increment i by page_size
            $page++;            // increment the page number

            //sleep(1);   // wait a second before hammering on their webserver again
        } while ($i < $count);

        return $collection;
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
