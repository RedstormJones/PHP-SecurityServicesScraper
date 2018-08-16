<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetClusterNodeStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:clusternodestats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query production elasticsearch cluster for node stats';

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
        // setup crawler
        $cookiejar = storage_path('app/cookies/elasticsearch_health.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup and apply auth header
        $headers = [
            'Authorization: Basic '.getenv('ELASTIC_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // get the url
        $cluster_url = getenv('ELASTIC_CLUSTER').'/_nodes/stats';

        // send request and capture JSON response
        $json_response = $crawler->get($cluster_url);
        file_put_contents(storage_path('app/responses/nodes_stats_response.json'), $json_response);

        // attempt to decode JSON response and pull out the nodes array
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $nodes = $response['nodes'];
        }
        catch (\Exception $e) {
            // if an exception is thrown then pop smoke and bail
            Log::error('[!] failed to JSON decode response: '.$e->getMessage());
            die('[!] failed to JSON decode response: '.$e->getMessage().PHP_EOL);
        }

        // build nodes_array
        $nodes_array = [];
        
        foreach ($nodes as $key => $node) {
            // $key is the unique id for this particular node, soo add it to the node object
            $node['node_id'] = $key;

            // add node to nodes_array
            $nodes_array[$node['name']] = $node;
        }

        // JSON encode and dump nodes_array to file
        file_put_contents(storage_path('app/collections/cluster_nodes_stats.json'), \Metaclassing\Utility::encodeJson($nodes_array));
    }
}
