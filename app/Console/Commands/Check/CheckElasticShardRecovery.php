<?php

namespace App\Console\Commands\Check;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckElasticShardRecovery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:shardrecovery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Elasticsearch shard recovery';

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
        Log::info(PHP_EOL.PHP_EOL.'******************************************'.PHP_EOL.'* Starting Elastic Shard Recovery Check! *'.PHP_EOL.'******************************************');

        // setup date and threshold variables
        $date = Carbon::now()->toDateString();

        // setup crawler
        $cookiejar = storage_path('app/cookies/elasticsearch_health.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup and apply auth header
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic '.getenv('ELASTIC_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // build the elastic url
        $es_url = getenv('ELASTIC_CLUSTER').'/*/_recovery?human&detailed=true&active_only=true';
        Log::info('[+] elastic shard recovery url: '.$es_url);

        // send request, capture response and dump to file
        $json_response = $crawler->get($es_url);
        file_put_contents(storage_path('app/responses/shard-recovery.json'), $json_response);

        try {
            // attempt to JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            // pop smoke and bail
            Log::error('[!] failed to decode JSON response: '.$e->getMessage());
            die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }

        // check array keys for error
        if (array_key_exists('error', $response)) {
            // pop smoke and bail
            $error = \Metaclassing\Utility::encodeJson(response['error']);
            Log::error('[!] '.$error);
            die('[!] '.$error);
        }

        // get the array keys from the reponse
        $indices = array_keys($response);

        // instantiate recoveries array
        $recoveries = [];

        // cycle through indices
        foreach ($indices as $index) {
            // get index data from response
            $index_data = $response[$index];

            // get shards array from index data
            $shards = $index_data['shards'];

            // cycle through shards array and build recoveries array
            foreach ($shards as $shard) {
                if ($shard['primary']) {
                    $shard_type = 'P';
                } else {
                    $shard_type = 'R';
                }

                if ($shard['stage'] == 'INDEX') {
                    $recoveries[] = [
                        'index'             => $index,
                        'shard_id'          => $shard['id'],
                        'shard_type'        => $shard_type,
                        'stage'             => $shard['stage'],
                        'source_host'       => $shard['source']['name'],
                        'target_host'       => $shard['target']['name'],
                        'total_size'        => $shard['index']['size']['total'],
                        'recovered_size'    => $shard['index']['size']['recovered'],
                        'percent_complete'  => $shard['index']['size']['percent'],
                    ];
                }
                elseif ($shard['stage'] == 'TRANSLOG') {
                    $recoveries[] = [
                        'index'             => $index,
                        'shard_id'          => $shard['id'],
                        'shard_type'        => $shard_type,
                        'stage'             => $shard['stage'],
                        'source_host'       => $shard['source']['name'],
                        'target_host'       => $shard['target']['name'],
                        'total_size'        => $shard['translog']['total'],
                        'recovered_size'    => $shard['translog']['recovered'],
                        'percent_complete'  => $shard['translog']['percent'],
                    ];
                }
            }
        }

        // log recoveries count and dump to file
        Log::info('[+] recoveries count: '.count($recoveries));
        file_put_contents(storage_path('app/collections/shard-recovery.json'), \Metaclassing\Utility::encodeJson($recoveries));

        // cycle through recoveries to build report string and output to console
        foreach ($recoveries as $r) {
            if ($r['stage'] == 'INDEX') {
                $r_string = $r['index']."\t".$r['stage']."\t\t".$r['shard_id']."\t".$r['shard_type']."\t".$r['source_host'].' =====> '.$r['target_host']."\t".$r['recovered_size'].'/'.$r['total_size']."\t\t".$r['percent_complete'];
            }
            elseif ($r['stage'] == 'TRANSLOG') {
                $r_string = $r['index']."\t".$r['stage']."\t".$r['shard_id']."\t".$r['shard_type']."\t".$r['source_host'].' =====> '.$r['target_host']."\t".$r['recovered_size'].'/'.$r['total_size']."\t".$r['percent_complete'];
            }
            
            $this->comment($r_string);
        }

        Log::info('[***] Elastic shard recovery command completed! [***]'.PHP_EOL);
    }
}
