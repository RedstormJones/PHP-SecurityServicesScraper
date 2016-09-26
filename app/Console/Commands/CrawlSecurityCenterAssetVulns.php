<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlSecurityCenterAssetVulns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:securitycenterassetvulns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run client to get asset vulnerability data from Security Center';

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
		$response_path = storage_path('app/responses/');

		// setup cookie jar to store cookies
		$cookiejar = storage_path('app/cookies/securitycenter_cookie.txt');
		$crawler = new \Crawler\Crawler($cookiejar);

		// build post assoc array using authentication info
		$post = [
		    'username' => getenv('SECURITYCENTER_USERNAME'),
		    'password' => getenv('SECURITYCENTER_PASSWORD'),
		];

		// set url to the token resource and post authentication data
		$url = 'https:/'.'/knetscalp001:443/rest/token';

		// capture response and dump to file
		$response = $crawler->post($url, "", $post);
		file_put_contents($response_path.'SC_login_assetvulns.dump', $response);

		// JSON decode response
		$resp = \Metaclassing\Utility::decodeJson($response);

		// extract token value from response and echo to console
		$token = $resp['response']['token'];

		// setup HTTP headers with token value
		$headers = array(
		    'X-SecurityCenter: '.$token,
		);
		curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

		// get them vulnerabilities, or the important ones at least
		$asset_collection = $this->getAssetSummary($crawler);

		// instantiate asset summary array
		$assetsummary = [];

		// cycle through asset collection and build simple array
		foreach($asset_collection as $result)
		{
		    foreach($result as $asset)
		    {
        		$assetsummary[] = $asset;
		    }
		}

		// JSON encode simple array and dump to file
		file_put_contents(storage_path('app/collections/sc_asset_summary.json'), \Metaclassing\Utility::encodeJson($assetsummary));
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
	public function getAssetSummary($crawler)
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
    	        'page'          => $page,
        	    'page_size'     => 1000,
            	'type'          => 'vuln',
	            'sourceType'    => 'cumulative',
    	        'query' => [
        	        'tool'      => 'sumasset',
            	    'type'      => 'vuln',
                	'filters'   => [
	                    [
    	                'filterName'=> 'exploitAvailable',
        	            'operator'  => '=',
            	        'value' => 'true',
                	    ],
	                ],
    	            'startOffset'   => $count,
        	        'endOffset'     => $endoffset,
            	],
	        ];

        	// send request for resource, capture response and dump to file
	        $response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));
			file_put_contents(storage_path('app/responses/SC_assetvulns.dump'.$page), $response);

			// JSON decode response
    	    $resp = \Metaclassing\Utility::decodeJson($response);

	        // extract vulnerability results and add to collection
    	    $collection[] = $resp['response']['results'];

        	// set total to the value of totalRecords in the response
	        $total = $resp['response']['totalRecords'];

        	// add number of returned records to count
	        $count += $resp['response']['returnedRecords'];

    	    // add number of returned records to new count value for next endoffset
        	$endoffset = $count + $resp['response']['returnedRecords'];

	        // increment page
    	    $page++;

        	// print some shit
	        echo 'count: '.$count.PHP_EOL;
    	    echo 'next endOffset: '.$endoffset.PHP_EOL;

    	    // wait a second before hammering the Security Center API again
        	sleep(1);
	    }
    	while($count < $total);

	    return $collection;

	}

}	// end of CrawlSecurityCenterAssetVulns command class
