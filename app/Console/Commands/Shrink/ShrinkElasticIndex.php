<?php

namespace App\Console\Commands\Shrink;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShrinkElasticIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shrink:indices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'utility command for shrinking Elasticsearch indices';

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
        Log::info(PHP_EOL.PHP_EOL.'#--------------------------------#'.PHP_EOL.'# Starting Elastic Index Shrink! #'.PHP_EOL.'#--------------------------------#');

        // set shrink node
        $shrink_node = 'secdatalp004';

        // get indices to shrink from file
        //$indices_str = file_get_contents(storage_path('app/collections/shrink.txt'));
        $json_indices = file_get_contents(storage_path('app/collections/shrink.json'));

        // use regex to split indice string into an array
        //$indices = preg_split('/$\R?^/m', $indices_str);
        $indices = \Metaclassing\Utility::decodeJson($json_indices);

        // setup cookie
        $cookiejar = storage_path('app/cookies/elastic_cookie.txt');

        foreach ($indices as $index) {
            // trim off new line
            $index = trim($index);

            // explode on '-' and build shrink index string
            $index_pieces = explode('-', $index);
            $shrink_index = $index_pieces[0].'_shrunk-'.$index_pieces[1];

            // log something
            Log::info('[+] starting shrink: '.$index.' ---> '.$shrink_index);

            /*
             *
             *  change index settings to move one copy of each shard to a single node and block write operations
             *
             */
            Log::info('[+] changing index settings to move a copy of each shard to a single node and block write operations...');

            // setup crawler
            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // setup PUT data
            $new_settings = [
                'settings'  => [
                    'index.routing.allocation.require._name'    => $shrink_node,
                    'index.blocks.write'                        => true,
                ],
            ];
            $json_new_settings = \Metaclassing\Utility::encodeJson($new_settings);

            // store PUT data to file
            file_put_contents(storage_path('app/responses/index_shrink_settings.json'), $json_new_settings);

            // use fopen to get a file pointer to PUT data file
            $fp = fopen(storage_path('app/responses/index_shrink_settings.json'), 'r');

            // setup index settings endpoint
            $url = getenv('ELASTIC_CLUSTER').'/'.$index.'/_settings';

            // send request and capture JSON response
            $json_response = $crawler->put($url, '', $fp);

            try {
                // attempt to JSON decode the response
                $response = \Metaclassing\Utility::decodeJson($json_response);
            } catch (\Exception $e) {
                // if we catch an exception then pop smoke and bail
                Log::error('[!] failed to decode JSON response: '.$e->getMessage());
                die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
            }

            // check for errors in the response
            $this->checkResponseError($response, '[!] something went wrong while relocating shards...');

            /*
             *
             * loop and query the index _recovery API for active recoveries until the response is empty JSON
             *
             */
            Log::info('[+] checking _recovery API for active recoveries...');

            // setup crawler
            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // instantiate relocation complete flag to false
            $relocation_complete = false;

            // setup index _recovery endpoint
            $url = getenv('ELASTIC_CLUSTER').'/'.$index.'/_recovery?human';

            do {
                // get index recovery info
                $json_response = $crawler->get($url);

                // instantiate shrink node count to 0
                $shrink_node_count = 0;

                try {
                    // attempt to JSON decode the response
                    $response = \Metaclassing\Utility::decodeJson($json_response);
                } catch (\Exception $e) {
                    // if we catch an exception then pop smoke and bail
                    Log::error('[!] failed to decode JSON response: '.$e->getMessage());
                    die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
                }

                // check response for errors
                $this->checkResponseError($response, '[!] failed to get recovery info on original index...');

                // get index array from response
                $index_array = $response[$index];

                // get shards array from index array
                $shards_array = $index_array['shards'];

                foreach ($shards_array as $shard) {
                    // check if shard stage is DONE
                    if ($shard['stage'] == 'DONE') {
                        // check if shard target is the shrink node
                        if ($shard['target']['name'] == $shrink_node) {
                            // increment shrink node count
                            $shrink_node_count++;
                        }
                    }
                }

                // if shrink node count is greater than or equal to 3 then we can assume we have a copy of each shard on the shrink node
                if ($shrink_node_count >= 3) {
                    // set relocation complete flag to true
                    Log::info('[+] shard relocation complete!');
                    $relocation_complete = true;
                } else {
                    // sleep for 15 seconds to give shard relocation some time
                    sleep(15);
                }
            } while (!$relocation_complete);

            /*
             *
             * shrink index, ensure number of shards and number of replicas are both set to 1 and best_compression is used
             *
             */
            Log::info('[+] shrinking index...');

            // setup crawler
            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // setup shrink url and post data
            $url = getenv('ELASTIC_CLUSTER').'/'.$index.'/_shrink/'.$shrink_index;

            $post_data = [
                'settings'  => [
                    'index.routing.allocation.require._name'    => null,
                    'index.blocks.write'                        => null,
                    'index.number_of_shards'                    => 1,
                    'index.number_of_replicas'                  => 1,
                    'index.codec'                               => 'best_compression',
                ],
            ];

            // post request and capture JSON response
            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post_data));

            try {
                // attempt to JSON decode the response
                $response = \Metaclassing\Utility::decodeJson($json_response);
            } catch (\Exception $e) {
                // if we catch an exception then pop smoke and bail
                Log::error('[!] failed to decode JSON response: '.$e->getMessage());
                die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
            }

            // check for errors in the response
            $this->checkResponseError($response, '[!] something went wrong while trying to shrink index...');

            /*
             *
             * loop and query the _recovery API on the shrunken index until the shard count in the response equals 2
             *
             */
            Log::info('[+] checking _recovery API on shrink index for active recoveries...');

            // setup crawler
            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // instantiate shrink complete flag to false
            $shrink_complete = false;

            // setup shrink index _recovery endpoint
            $url = getenv('ELASTIC_CLUSTER').'/'.$shrink_index.'/_recovery?human';

            do {
                $json_response = $crawler->get($url);

                // instantiate shrink node count to 0
                $shrink_node_count = 0;

                try {
                    // attempt to JSON decode the response
                    $response = \Metaclassing\Utility::decodeJson($json_response);
                } catch (\Exception $e) {
                    // if we catch an exception then pop smoke and bail
                    Log::error('[!] failed to decode JSON response: '.$e->getMessage());
                    die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
                }

                // check response for errors
                $this->checkResponseError($response, '[!] failed to get recovery info on shrink index...');

                // get index array from response
                $index_array = $response[$shrink_index];

                // get shards array from index array
                $shards_array = $index_array['shards'];

                // check if the shards array count is equal to 2
                if (count($shards_array) == 2) {
                    foreach ($shards_array as $shard) {
                        // check if the shard stage is DONE
                        if ($shard['stage'] == 'DONE') {
                            // increment shrink node count
                            $shrink_node_count++;
                        }
                    }

                    if ($shrink_node_count == 2) {
                        // set shrink complete flag to true
                        Log::info('[+] shrink finished!');
                        $shrink_complete = true;
                    } else {
                        // sleep for 15 seconds to give shrinking some time
                        sleep(15);
                    }
                } else {
                    // sleep for 15 seconds to give shrinking some time
                    sleep(15);
                }
            } while (!$shrink_complete);

            /*
             *
             * delete the original index
             *
             */
            Log::info('[+] deleting the original index...');

            // 30 second sleep to allow for any active shrink operations to complete
            sleep(30);

            // setup delete url
            $url = getenv('ELASTIC_CLUSTER').'/'.$index;

            // send request and capture JSON response
            $json_response = $crawler->delete($url);

            try {
                // attempt to JSON decode the response
                $response = \Metaclassing\Utility::decodeJson($json_response);
            } catch (\Exception $e) {
                // if we catch an exception then pop smoke and bail
                Log::error('[!] failed to decode JSON response: '.$e->getMessage());
                die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
            }

            // check for errors in the response
            $this->checkResponseError($response, '[!] something went wrong trying to delete original index!');

            Log::info('[###] Elastic Index Shrink completed! [###]'.PHP_EOL);
        }
    }

    /**
     * Function to check Elasticsearch responses (as arrays) for the existence of an 'error' key. If response contains
     * the key 'error' then dump it to file and pop smoke.
     *
     * @return null
     */
    public function checkResponseError($response, $err_msg)
    {
        if (array_key_exists('error', $response)) {
            Log::error($err_msg);

            file_put_contents(storage_path('app/responses/elastic-error.json'), \Metaclassing\Utility::encodeJson($response));

            die($err_msg.PHP_EOL);
        }
    }
}
