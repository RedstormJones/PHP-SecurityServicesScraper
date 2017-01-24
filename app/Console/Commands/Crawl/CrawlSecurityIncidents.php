<?php

namespace App\Console\Commands\Crawl;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlSecurityIncidents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:securityincidents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run client to get security incidents from ServiceNow';

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
        // setup cookie jar
        $cookiejar = storage_path('app/cookies/servicenow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url to incidents table and add necessary query params
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/incident?sysparm_display_value=true&assignment_group=IM%20SEC%20-%20Security';

        // setup HTTP headers with basic auth
        $headers = [
            'accept: application/json',
            'authorization: Basic '.getenv('SERVICENOW_AUTH'),
            'cache-control: no-cache',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // send request and capture response
        $response = $crawler->get($url);

        // dump response to file
        file_put_contents(storage_path('app/responses/security_incidents.dump'), $response);

        // JSON decode response
        $security_incidents = \Metaclassing\Utility::decodeJson($response);

        // grab the data we care about and tell the world how many incidents we have
        $incidents = $security_incidents['result'];
        echo 'total incident count: '.count($incidents).PHP_EOL;

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/security_incidents_collection.json'), \Metaclassing\Utility::encodeJson($incidents));
    }
}
