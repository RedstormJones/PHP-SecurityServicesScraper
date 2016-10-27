<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlCMDBServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:cmdbservers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run client to pull CMDB server records from ServiceNow';

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
        // setup cookiejar
        $cookiejar = storage_path('app/cookies/snow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url to CMDB server list
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/cmdb?sysparm_display_value=true&sys_class_name=Server';

        // setup HTTP headers and add them to crawler
        $headers = [
            'accept: application/json',
            'authorization: Basic T0RCQ1JlcG9ydDpPREJDUmVwb3J0',
            'cache-control: no-cache',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // send request and capture response
        $response = $crawler->get($url);

        // dump response to file
        file_put_contents(storage_path('app/responses/cmdb.response'), $response);

        // JSON decode response
        $cmdb_servers = \Metaclassing\Utility::decodeJson($response);

        // get the data we care about and tell the world how many records we got
        $servers = $cmdb_servers['result'];
        echo 'total server count: '.count($servers).PHP_EOL;

        // JSON encode and dump CMDB servers to file
        file_put_contents(storage_path('app/collections/cmdb_servers_collection.json'), \Metaclassing\Utility::encodeJson($servers));
    }
}
