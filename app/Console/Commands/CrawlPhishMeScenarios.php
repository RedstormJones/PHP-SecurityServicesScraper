<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlPhishMeScenarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:phishmescenarios';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run client to get scenario data from PhishMe';

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
		// setup tries, cookiejar, and response path variables
		$tries = 0;
		$cookiejar = storage_path('app/cookies/phishme_cookie.txt');
		$response_path = storage_path('app/responses/');

		// instantiate crawler
		$crawler = new \Crawler\Crawler($cookiejar);

		// setup HTTP headers with token
		$token = getenv('PHISHME_TOKEN');
		$headers = array(
		    'Authorization: Token token='.$token,
		);
		curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

		// point url to scenarios
		$url = 'https:/'.'/login.phishme.com/api/v1/scenarios.json';

		// capture response and dump to file
		$jsonresponse = $crawler->get($url);
		file_put_contents($response_path.'phishme_scenarios.json', $jsonresponse);

		// if response contains Access denied or 403 Forbidden and tries is less than 100 then keep trying
		while(preg_match('/.+(Access denied.)|(403 Forbidden).+/', $jsonresponse, $hits) && $tries < 100)
		{
		    $jsonresponse = $crawler->get($url);
		    file_put_contents($response_path.'phishme_scenarios.json', $jsonresponse);

		    $tries++;
		}
		// if we exit the loop and tries is at 100 then bail
		if($tries == 100)
		{
		    die('Error: Could not get list of PhishMe scenarios');
		}

		// otherwise we should have a good list of scenarios now
		$response = \Metaclassing\Utility::decodeJson($jsonresponse);

		// instantiate collection array
		$collection = [];

		// cycle through scenarios and download full csv for each
		foreach($response as $scenario)
		{
			// grab scenario id and type from each scenario
		    $scenario_id = $scenario['id'];
		    $scenario_type = str_replace(' ', '', $scenario['scenario_type']);

			// piont url to scenario full csv download
		    $url = 'https:/'.'/login.phishme.com/api/v1/scenario/'.$scenario_id.'/full_csv';

			// capture response and dump to file
		    $response = $crawler->get($url);
		    file_put_contents($response_path.'phishme_scenario_'.$scenario_id.'.csv', $response);

			// if we get access denied or 403 forbidden then wait 3 seconds and try again
		    while(preg_match('/.+(Access denied.)|(403 Forbidden).+|(API Token Busy:).+/', $response, $hits))
		    {
        		sleep(3);

		        $response = $crawler->get($url);
		        file_put_contents($response_path.'phishme_scenario_'.$scenario_id.'.csv', $response);
		    }

		    // instantiate keys and newArray arrays
		    $keys = [];
		    $newArray = [];

		    // convert csv data to workable array
		    $data = $this->csvToArray(storage_path('app/responses/phishme_scenario_'.$scenario_id.'.csv'), ',');

		    // Set number of elements (minus 1 because we shift off the first row)
		    $count = count($data) - 1;

		    // create keys using first row
		    $labels = array_shift($data);

		    echo 'Creating keys..'.PHP_EOL;
		    foreach ($labels as $label)
		    {
        		$keys[] = $label;
		    }

			// add keys for scenario type and id
		    $keys[] = 'scenario_type';
		    $keys[] = 'scenario_id';

		    // Bring it all together
		    echo 'Building associative array..'.PHP_EOL;
		    for ($j = 0; $j < $count; $j++)
		    {
        		array_push($data[$j], 'App\PhishMe\\'.$scenario_type.'Scenario');
		        array_push($data[$j], $scenario_id);

        		$d = array_combine($keys, $data[$j]);
		        $newArray[$j] = $d;
		    }

			// cycle through newArray and build collectoin
		    foreach($newArray as $scenario)
		    {
				// this creates a unique scenario id for each element of each scenario
        		$scenario['scenario_id'] = $scenario_id.':'.$scenario['Recipient Name'];
        		$collection[] = $scenario;
		    }

		}

		// dump collection to file
		file_put_contents(storage_path('app/collections/scenario_collection.json'), json_encode($collection));

    }	// end of handle function


	/**
	* Function to convert csv data to usable array
	*
	* @return array
	*/
	public function csvToArray($file, $delimiter)
	{
    	if (($handle = fopen($file, 'r')) !== false)
	    {
    	    $i = 0;

        	while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== false)
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

	}	// end of csvToArray function

}	// end of CrawlPhishMeScenarios command class
