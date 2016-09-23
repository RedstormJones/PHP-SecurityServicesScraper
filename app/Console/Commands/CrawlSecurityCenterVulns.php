<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');


use Illuminate\Console\Command;

class CrawlSecurityCenterVulns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:securitycentervulns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run client to get vulnerability data from Security Center';

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
        $response_path = storage_path('logs/responses/');

        // setup cookie jar to store cookies
        $cookiejar = storage_path('logs/cookies/securitycenter_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // build post assoc array using authentication info
        $post = [
            'username' => getenv('SECURITYCENTER_USERNAME'),
            'password' => getenv('SECURITYCENTER_PASSWORD'),
        ];

        // set url to the token resource
        $url = 'https:/'.'/knetscalp001:443/rest/token';

        // post authentication data, capture response and dump to file
        $response = $crawler->post($url, '', $post);
        file_put_contents($response_path.'SC_login.dump', $response);

        // JSON decode response
        $resp = \Metaclassing\Utility::decodeJson($response);

        // extract token value from response and echo to console
        $token = $resp['response']['token'];

        // setup HTTP headers with token value
        $headers = [
            'X-SecurityCenter: '.$token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // get them vulnerabilities, or the important ones at least
        $medium_collection = $this->getVulnsBySeverity($crawler, 2);           // medium severity vulnerabilities
        $high_collection = $this->getVulnsBySeverity($crawler, 3);          // high severity vulnerabilities
        $critical_collection = $this->getVulnsBySeverity($crawler, 4);      // critical severity vulnerabilities

        // instantiate severity arrays
        $critical_vulns = [];
        $high_vulns = [];
        $medium_vulns = [];

        // cycle through critical vulnerabilities and build simple array
        foreach ($critical_collection as $result) {
            foreach ($result as $vuln) {
                $critical_vulns[] = $vuln;
            }
        }

        // cycle through high vulnerabilities and build simple array
        foreach ($high_collection as $result) {
            foreach ($result as $vuln) {
                $high_vulns[] = $vuln;
            }
        }

        // cycle through medium vulnerabilities and build simple array
        foreach ($medium_collection as $result) {
            foreach ($result as $vuln) {
                $medium_vulns[] = $vuln;
            }
        }

        /*
        echo 'critical vulnerability count: '.count($critical_vulns).PHP_EOL;
        echo 'high vulnerability count:     '.count($high_vulns).PHP_EOL;
        echo 'medium vulnerability count:   '.count($medium_vulns).PHP_EOL;
        /**/

        // dump data to file
        file_put_contents(storage_path('logs/collections/sc_medvulns_collection.json'), \Metaclassing\Utility::encodeJson($medium_vulns));
        file_put_contents(storage_path('logs/collections/sc_highvulns_collection.json'), \Metaclassing\Utility::encodeJson($high_vulns));
        file_put_contents(storage_path('logs/collections/sc_criticalvulns_collection.json'), \Metaclassing\Utility::encodeJson($critical_vulns));
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

    /**
     * Function to pull vulnerability data from Security Center 5.4.0 API.
     *
     * @return array
     */
    public function getVulnsBySeverity($crawler, $severity)
    {
        // point url to the resource we want
        $url = 'https:/'.'/knetscalp001:443/rest/analysis';

        // instantiate the collections array, set count to 0 and set endoffset to 1000 (since pagesize = 1000)
        $collection = [];
        $count = 0;
        $endoffset = 1000;
        $page = 1;

        echo 'starting page scrape..'.PHP_EOL;

        do {
            // setup post array
            $post = [
                'page'          => 'all',
                'page_size'     => 1000,
                'type'          => 'vuln',
                'sourceType'    => 'cumulative',
                'query'         => [
                    'tool'      => 'vulndetails',
                    'type'      => 'vuln',
                    'filters'   => [
                        [
                        'filterName' => 'severity',
                        'operator'   => '=',
                        'value'      => $severity,
                        ],
                    ],
                    'startOffset'   => $count,
                    'endOffset'     => $endoffset,
                ],
            ];

            // send request for resource, capture response and dump to file
            $response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));
            file_put_contents(storage_path('logs/responses/SC_vulns.dump'.$page), $response);

            // JSON decode response
            $resp = \Metaclassing\Utility::decodeJson($response);

            // extract vulnerability results and add to collection
            $collection[] = $resp['response']['results'];

            // set total to the value of totalRecords in the response
            $total = $resp['response']['totalRecords'];

            echo 'received '.count($collection).' of '.$total.' records'.PHP_EOL;

            // add number of returned records to count
            $count += $resp['response']['returnedRecords'];

            // add number of returned records to new count value for next endoffset
            $endoffset = $count + $resp['response']['returnedRecords'];

            // print some shit
            //echo 'count: '.$count.PHP_EOL;
            //echo 'next endOffset: '.$endoffset.PHP_EOL;

            // these vars may end up holding a massive amount of data per request,
            // so set them to null at the end of each iteration
            $response = null;
            $resp = null;

            $page++;

            // wait a second before hammering the Security Center API again
            //sleep(1);
        } while ($count < $total);

        // tell the world you're done
        //echo 'Done - total sev'.$severity.' records: '.count($collection).PHP_EOL;

        return $collection;
    }
}
