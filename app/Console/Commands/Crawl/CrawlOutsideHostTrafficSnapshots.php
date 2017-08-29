<?php

namespace App\Console\Commands\Crawl;

use Illuminate\Console\Command;

class CrawlOutsideHostTrafficSnapshots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:outsidehosttrafficsnapshots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run client to get snapshots of application traffic from outside hosts';

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
        // setup cookiejar and domain id
        $cookiejar = storage_path('app/cookies/smc_cookie.txt');
        $domainID = 123;

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url at authentication service
        $url = getenv('LANCOPE_URL').'/smc/j_spring_security_check';
        echo 'url: '.$url.PHP_EOL;

        // put authentication data together
        $post = [
            'j_username'    => getenv('LANCOPE_USERNAME'),
            'j_password'    => getenv('LANCOPE_PASSWORD'),
        ];

        // post authentication data to service, capture response and dump to file
        $response = $crawler->post($url, '', $this->postArrayToString($post));
        file_put_contents(storage_path('app/responses/smc.auth.dump'), $response);

        // point url to dashboard to get app traffic snapshots for default hostgroup
        $url = getenv('LANCOPE_URL').'/smc/rest/domains/123/hostgroups/0/applicationTraffic';
        echo 'url: '.$url.PHP_EOL;

        // send request, capture response and dump it to file
        $response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/outsidehost_apptraffic_dump.json'), $response);

        // JSON decode response into an array
        $response_arr = \Metaclassing\Utility::decodeJson($response);
        echo 'count: '.count($response_arr).PHP_EOL;

        // instantiate collection array and setup regexes
        $outsidehost_apptraffic_collection = [];
        $trim_regex = '/(.+)\..+/';
        $replace_regex = '/T/';

        // cycle through response array
        foreach ($response_arr as $response) {
            // grab the data we care about and the time period value
            $app_dashboard = $response['applicationTrafficPerApplication'];
            $timePeriod = $response['timePeriod'];

            // format time period value to Y-m-d H:i:s
            preg_match($trim_regex, $timePeriod, $hits);
            $time_period = preg_replace($replace_regex, ' ', $hits[1]);

            // cycle through data and build collections array
            foreach ($app_dashboard as $app) {
                $app['timePeriod'] = $time_period;
                $outsidehost_apptraffic_collection[] = $app;
            }
        }

        // tell the world your collection count
        echo 'collection count: '.count($outsidehost_apptraffic_collection).PHP_EOL;

        // JSON encode and dump collection to file
        file_put_contents(storage_path('app/collections/outsidehost_apptraffic.json'), \Metaclassing\Utility::encodeJson($outsidehost_apptraffic_collection));
    }

    // end of function handle()

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

    // end of function postArrayToString()
}   // end of CrawlOutsideHostTrafficSnapshots command class
