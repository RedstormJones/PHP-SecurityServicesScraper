<?php

namespace App\Console\Commands;

require_once(app_path('Console/Crawler/Crawler.php'));

use Illuminate\Console\Command;

class CrawlSpamEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:spamemails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl IronPort web console and parse out spam email statistics';

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
		$username = getenv('IRONPORT_USERNAME');
		$password = getenv('IRONPORT_PASSWORD');

		$response_path = storage_path('logs/responses/');

		// setup cookiejar file
		$cookiejar = storage_path('logs/cookies/ironport_cookie.txt');
		echo 'Storing cookies at '.$cookiejar.PHP_EOL;

		// instantiate crawler object
		$crawler = new \Crawler\Crawler($cookiejar);

		// set url
		$url = 'https:/'.'/dh1146-sma1.iphmx.com';

		// hit webpage and try to capture CSRF token, otherwise die
		$response = $crawler->get($url);
		file_put_contents($response_path.'ironport_login.spam.dump', $response);

		// set regex string to dashboard page <title> element
		$regex = '/(<title>        Cisco         Content Security Management Appliance   M804 \(dh1146-sma1\.iphmx\.com\) -         Centralized Services &gt; System Status <\/title>)/';
		$tries = 0;

		// while NOT at the dashboard page
		while(!preg_match($regex, $response, $hits) && $tries <= 3)
		{
		    // find CSRFKey value
		    $regex = '/CSRFKey=([\w-]+)/';

		    if(preg_match($regex, $response, $hits))
		    {
		        $csrftoken = $hits[1];
		    }
		    else {
        		die('Error: could not get CSRF token'.PHP_EOL);
		    }

		    // set login URL and post data
		    $url = 'https:/'.'/dh1146-sma1.iphmx.com/login';

		    $post = [
        	    'action'    => 'Login',
            	'referrer'  => 'https:/'.'/dh1146-sma1.iphmx.com/default',
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
		if($tries > 3) {
		    die('Error: could not post successful login within 3 attempts'.PHP_EOL);
		}

		// if we made it here then we've successfully logged in, so tell someone about it
		echo 'Login successful...'.PHP_EOL;

		// dump dashboard to file
		file_put_contents($response_path.'ironport_dashboard.dump', $response);

		// now that we're in head over to the local quarantines
		$url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email_quarantine/local_quarantines';

		// capture response and try to extract CSRF token (it might be a new one)
		$response = $crawler->get($url);
		$regex = "/CSRFKey = '(.+)'/";
		if(preg_match($regex, $response, $hits))
		{
		    $csrftoken = $hits[1];
		    echo 'Found CSRF token: '.$csrftoken.PHP_EOL;
		}
		else {
		    // if no CSRFToken, pop smoke
		    die('Error: could not get CSRF Token'.PHP_EOL);
		}

		echo 'Starting incoming email scrape'.PHP_EOL;

		// setup url and referer to go to the Centralized Policy spam quarantine
		$url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email_quarantine/local_quarantines_dosearch?';
		$referer = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email_quarantine/local_quarantines';
		$refparams = [
        	'CSRFKey'       => $csrftoken,
	        'clear'         => 'true',
    	    'name'          => 'Policy',
        	'mquar_sort'    => 'time_desc',
		];

		// append GET parameters to url and send request to web server
		$spam_url = $url . $this->postArrayToString($refparams);
		$response = $crawler->get($spam_url, $referer);
		file_put_contents($response_path.'ironport_localquarantines.dump', $response);

		// find time_stamp value in response
		$regex = "/time_stamp=(\d+.\d+)/";
		if(preg_match($regex, $response, $hits))
		{
		    $time_stamp = $hits[1];
		}
		else {
		    // if no time_stamp value then no working request so die
		    die('Error: could not get time_stamp'.PHP_EOL);
		}

		// create necessary HTTP headers and configure curl with them
		$headers = [
		    'X-Requested-With: XMLHttpRequest',
		];

		curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

		// setup url spam search url
		$url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email_quarantine/local_quarantines_dosearch?';

		$collection = [];
		$page = 1;
		$i = 0;

		echo 'Starting page scrape loop'.PHP_EOL;
		do {
		    echo 'Scraping loop for page '.$page.PHP_EOL;

		    // setup GET parameters and append to spam search url
		    $getmessages = [
        		'action'    => 'GetMessages',
		        'CSRFKey'   => $csrftoken,
    		    'name'      => 'Policy',
        		'key'       => 'time_added',
	        	'dir'       => 'desc',
	    	    'time_stamp'=> $time_stamp,
    	    	'pg'        => $page,
	    	    'pageSize'  => '20',
    		];

		    $geturl = $url . $this->postArrayToString($getmessages);

		    // capture reponse and dump to file
		    $response = $crawler->get($geturl, $referer);
		    file_put_contents($response_path.'spam.dump.'.$page, $response);

		    // JSON decode the response and add it to the spam collection
		    $spam = \Metaclassing\Utility::decodeJson($response);
		    $collection[] = $spam;

		    echo 'Scrape for page '.$page.' complete, got '.count($spam).' spam records'.PHP_EOL;

		    // set count to total number of messages
		    $count = $spam['num_msgs'];

		    $i += 20;   // increment by number of records per page
		    $page++;    // increment to next page

		    // sleep for 1 second before hammering on IronPort again
		    sleep(1);
		}
		while($i < $count);

		$spam_emails = [];

		// first level is simple sequencail array of 1,2,3
		foreach($collection as $response) {
		    // next level down is associative, the KEY we care about is 'Data'
		    $results = $response['search_result'];
		    foreach($results as $spammer) {
		        // this is confusing logic.
		        $spam_emails[] = $spammer;
		    }
		}

		// Now we ahve a simple array [1,2,3] of all the threat records,
		// each threat record is a key=>value pair collection / assoc array
		//\Metaclassing\Utility::dumper($spam_emails);
		file_put_contents(storage_path('logs/collections/spam.json'), \Metaclassing\Utility::encodeJson($spam_emails));
    }

	/**
	* Function to convert post information from an assoc array to a string
	*
	* @return string
	*/
	function postArrayToString($post)
	{
	    $postarray = [];

    	foreach($post as $key => $value) { $postarray[] = $key . '=' . $value; }

	    $poststring = implode('&', $postarray);

    	return $poststring;
	}

}
