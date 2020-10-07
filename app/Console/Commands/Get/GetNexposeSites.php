<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetNexposeSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:nexposesites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get site data from Nexpose API';

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
        Log::info('[GetNexposeSites.php] Starting Nexpose Sites API Poll!');

        // response path
        $response_path = storage_path('app/responses/');

        // cookie jar
        $cookiejar = storage_path('app/cookies/nexpose_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // set url
        $nexpose_url = getenv('NEXPOSE_URL2');

        // get creds and build auth string
        $username = getenv('NEXPOSE_USERNAME');
        $password = getenv('NEXPOSE_PASSWORD');

        $auth_str = base64_encode($username.':'.$password);

        // auth header
        $headers = [
            'Authorization: Basic '.$auth_str,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // instantiate collection array
        $collection = [];

        // setup nexpose sites url and log it
        $url = $nexpose_url.'/sites?size=500';

        // capture JSON response and dump to file
        Log::info('[GetNexposeSites.php] sending GET request to: '.$url);
        $json_response = $crawler->get($url);
        file_put_contents($response_path.'nexpose_sites.json', $json_response);

        try {
            // attempt to JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info('[GetNexposeSites.php] collected '.count($response['resources']).' sites from Nexpose...');
        } catch (\Exception $e) {
            Log::error('[GetNexposeSites.php] ERROR failed to decode JSON response: '.$e->getMessage());
            die('[GetNexposeSites.php] ERROR failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }
        // instantiate sites array
        $sites_array = [];

        foreach ($response['resources'] as $site) {
            $site_nolinks = array_except($site, ['links']);

            $site_id = array_pull($site_nolinks, 'id');
            $site_nolinks['site_id'] = $site_id;

            $site_type = array_pull($site_nolinks, 'type');
            $site_nolinks['site_type'] = $site_type;

            if (array_key_exists('lastScanTime', $site)) {
                $last_scan_time = $site['lastScanTime'];
            } else {
                $last_scan_time = null;
            }

            $sites_array[] = [
                'site_id'           => $site['id'],
                'site_name'         => $site['name'],
                'site_type'         => $site['type'],
                'importance'        => $site['importance'],
                'assets'            => $site['assets'],
                'scan_engine'       => $site['scanEngine'],
                'scan_template'     => $site['scanTemplate'],
                'risk_score'        => $site['riskScore'],
                'last_scan_time'    => $last_scan_time,
                'vulnerabilities'   => $site['vulnerabilities'],
            ];
        }

        file_put_contents(storage_path('app/collections/nexpose-sites.json'), \Metaclassing\Utility::encodeJson($sites_array));

        Log::info('[GetNexposeSites.php] sending '.count($sites_array).' nexpose sites to kafka...');

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        foreach ($sites_array as $site) {
            // add upsert datetime
            $site['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'nexpose',
                    'value' => \Metaclassing\Utility::encodeJson($site),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[GetNexposeSites.php] ERROR sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            }
        }

        Log::info('[GetNexposeSites.php] Nexpose Sites completed!');
    }
}
