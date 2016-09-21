<?php

namespace App\Console\Commands;

require_once(dirname(__DIR__).'/globals.php');
require_once(CRAWLERS.'Crawler.php');

use Illuminate\Console\Command;

class CrawlIncomingEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:incomingemail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl IronPort web console and parse out incoming email statistics';

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

		$outputfile = '/opt/application/collections/incoming_email.csv';

		// setup cookiejar file
		$cookiejar = CRAWLERS.'cookies/ironport_cookie.txt';
		echo 'Storing cookies at '.$cookiejar.PHP_EOL;

		// instantiate crawler object
		$crawler = new \Crawler\Crawler($cookiejar);

		// set url
		$url = 'https:/'.'/dh1146-sma1.iphmx.com';

		// hit webpage and try to capture CSRF token, otherwise die
		$response = $crawler->get($url);

		// set regex string to dashboard page <title> element
		$regex = '/(<title>        Cisco         Content Security Management Appliance   M804 \(dh1146-sma1\.iphmx\.com\) -         Centralized Services &gt; System Status <\/title>)/';
		$tries = 0;
		// while NOT at the dashboard page
		while(!preg_match($regex, $response, $hits) && $tries <= 3)
		{
		    // find CSRFKey value
		    $regex = '/CSRFKey=([\w-]+)/';
		    //$regex = '/CSRFKey=(.+\b&)/';
		    //echo $regex.PHP_EOL;

		    if(preg_match($regex, $response, $hits))
		    {
		        $csrftoken = $hits[1];
        		//$csrftoken = rtrim($csrftoken, '&');
		        echo 'Found CSRF token: '.$csrftoken.PHP_EOL;
		    }
		    else {
		        die('Error: could not get CSRF token'.PHP_EOL);
		    }

		    // set login url and post data
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
		echo 'Logged In'.PHP_EOL;

		// set url to go to Email
		$url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email/user_report';

		// capture response and try to extract new CSRF token, otherwise die
		$response = $crawler->get($url);
		$regex = "/CSRFKey = '(.+)'/";
		if(preg_match($regex, $response, $hits))
		{
		    $csrftoken = $hits[1];
		}
		else {
		    die('Error: could not get CSRF Token'.PHP_EOL);
		}

		echo 'Starting incoming email scrape'.PHP_EOL;

		// set incoming email download url and post data
		$url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email/mail_reports/incoming_mail';

		$post = [
        	'profile_type'      => 'domain',
	        'format'            => 'csv',
    	    'CSRFKey'           => $csrftoken,
        	'report_query_id'   => 'sma_incoming_mail_domain_search',
	        'date_range'        => 'current_day',
    	    'report_def_id'     => 'sma_incoming_mail',
        ];

		// capture reponse and dump to file
		$response = $crawler->post($url, $url, $this->postArrayToString($post));
		file_put_contents($outputfile, $response);

		// Arrays we'll use later
		$keys = array();
		$newArray = array();

		// Do it
		$data = $this->csvToArray($outputfile, ',');

		// Set number of elements (minus 1 because we shift off the first row)
		$count = count($data) - 1;
		echo 'Read '.$count.' incoming email records'.PHP_EOL;

		//Use first row for names
		$labels = array_shift($data);

		echo 'Creating keys..'.PHP_EOL;
		foreach ($labels as $label)
		{
		    $keys[] = $label;
		}

		// Bring it all together
		echo 'Building associative array..'.PHP_EOL;
		for ($j = 0; $j < $count; $j++)
		{
		    $d = array_combine($keys, $data[$j]);
		    $newArray[$j] = $d;
		}

		// JSON encode data and dump to file
		$jsonfilename = '/opt/application/collections/incoming_email.json';
		file_put_contents($jsonfilename, json_encode($newArray));
		echo 'Finished - JSON formatted email data stored in '.$jsonfilename.PHP_EOL;
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

		$poststring = implode('&', $postarray);

		return $poststring;
	}


	/**
	* Function to convert CSV into associative array
	*
	* @return array
	*/
	function csvToArray($file, $delimiter)
	{
    	if (($handle = fopen($file, 'r')) !== FALSE)
	    {
    	    $i = 0;

        	while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE)
	        {
    	        for ($j = 0; $j < count($lineArray); $j++)
        	    {
            	    $arr[$i][$j] = $lineArray[$j];
	            }
    	        $i++;
        	}

	        fclose($handle);
    	}

	    return $arr;
	}

}
