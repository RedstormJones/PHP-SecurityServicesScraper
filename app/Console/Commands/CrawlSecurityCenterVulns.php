<?php

namespace App\Console\Commands;

require_once(dirname(__DIR__).'/globals.php');
require_once(CRAWLERS.'Crawler.php');

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
		// setup cookie jar to store cookies
		$cookiejar = 'cookie.txt';
		$crawler = new \Crawler\Crawler($cookiejar);

		// build post assoc array using authentication info
		$post = [
		    'username' => getenv('SECURITYCENTER_USERNAME'),
		    'password' => getenv('SECURITYCENTER_PASSWORD'),
		];

		// set url to the token resource and post authentication data
		$url = 'https:/'.'/knetscalp001:443/rest/token';
		$response = $crawler->post($url, "", $post);

		// capture response and JSON decode it
		$resp = \Metaclassing\Utility::decodeJson($response);

		// extract token value from response and echo to console
		$token = $resp['response']['token'];

		// setup HTTP headers with token value
		$headers = array(
		    'X-SecurityCenter: '.$token,
		);
		curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

		// get them vulnerabilities, or the important ones at least
		$medium_collection = $this->getVulnsBySeverity($crawler, 2);           // medium severity vulnerabilities
		$high_collection = $this->getVulnsBySeverity($crawler, 3);          // high severity vulnerabilities
		$critical_collection = $this->getVulnsBySeverity($crawler, 4);      // critical severity vulnerabilities

		$critical_vulns = [];
		$high_vulns = [];
		$medium_vulns = [];

		foreach($critical_collection as $result)
		{
		    foreach($result as $vuln)
		    {
        		$critical_vulns[] = $vuln;
		    }
		}

		foreach($high_collection as $result)
		{
		    foreach($result as $vuln)
		    {
        		$high_vulns[] = $vuln;
		    }
		}

		foreach($medium_collection as $result)
		{
		    foreach($result as $vuln)
		    {
        		$medium_vulns[] = $vuln;
		    }
		}

		echo 'critical vulnerability count: '.count($critical_vulns).PHP_EOL;
		echo 'high vulnerability count:     '.count($high_vulns).PHP_EOL;
		echo 'medium vulnerability count:   '.count($medium_vulns).PHP_EOL;

		// dump data to file
		file_put_contents('/opt/application/collections/sc_medvulns_collection.json', \Metaclassing\Utility::encodeJson($medium_vulns));
		file_put_contents('/opt/application/collections/sc_highvulns_collection.json', \Metaclassing\Utility::encodeJson($high_vulns));
		file_put_contents('/opt/application/collections/sc_criticalvulns_collection.json', \Metaclassing\Utility::encodeJson($critical_vulns));
    }

	/**
	* Function to convert post information from an assoc array to a string
	*
	* @return string
	*/
	public function postArrayToString($post)
	{
	    $postarray = [];
    	foreach($post as $key => $value) { $postarray[] = $key . '=' . $value; }

	    // takes the postarray array and concatenates together the values with &'s
    	$poststring = implode('&', $postarray);

	    return $poststring;
	}

	/**
	* Function to pull vulnerability data from Security Center 5.4.0 API
	*
	* @return array
	*/
	public function getVulnsBySeverity($crawler, $severity)
	{
    	// setup output file
	    $outputfile = '/opt/application/collections/sc_vulns_sev'.$severity.'.json';

    	// point url to the resource we want
	    $url = 'https:/'.'/knetscalp001:443/rest/analysis';

    	// instantiate the collections array, set count to 0 and set endoffset to 1000 (since pagesize = 1000)
	    $collection = [];
    	$count = 0;
	    $endoffset = 1000;

    	echo 'starting page scrape..'.PHP_EOL;

	    do {
        	// setup post array
    	    $post = [
	            'page'          => 'all',
            	'page_size'     => 1000,
        	    'type'          => 'vuln',
    	        'sourceType'    => 'cumulative',
	            'query' => [
            	    'tool'      => 'vulndetails',
        	        'type'      => 'vuln',
    	            'filters'   => [
	                    [
                    	'filterName'=> 'severity',
                	    'operator'  => '=',
            	        'value' => $severity,
        	            ]
    	            ],
	                'startOffset'   => $count,
                	'endOffset'     => $endoffset,
            	],
        	];
    	    //print_r($post);

	        // send request for resource, decode response and dump to console
        	$response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));
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
	        // so set them to null at the end of each iteration - supposed to help
        	// with memory usage, but we'll see
    	    $response = NULL;
	        $resp = NULL;

	        // wait a second before hammering the Security Center API again
        	//sleep(1);
    	}
	    while($count < $total);

    	// tell the world you're done
	    echo 'Done - total sev'.$severity.' records: '.count($collection).PHP_EOL;

    	return $collection;
	}
}
