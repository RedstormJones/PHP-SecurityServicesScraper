<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCASAlertsHigh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:casalertshigh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query Microsoft cloud app security for high alerts';

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
        // setup file to hold cookie
        $cookiejar = storage_path('app/cookies/cylancecookie.txt');

        // create crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        $cas_token = getenv('CAS_TOKEN');
        $cas_url = getenv('CAS_URL').'/api/v1/alerts/';

        // use javascript token to setup necessary HTTP headers
        $headers = [
            'Authorization: Token '.$cas_token,
        ];

        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $post_data = [
            'filters'   => [
                'severity' => [
                    'eq'    => [2]
                ]
            ]
        ];

        $response = $crawler->post($cas_url, '', \Metaclassing\Utility::encodeJson($post_data));
        file_put_contents(storage_path('app/responses/cas_alerts_high.json'), $response);

        $data = \Metaclassing\Utility::decodeJson($response);
        $high_alerts = $data['data'];

        $alerts = [];

        foreach ($high_alerts as $alert)
        {
            if (isset($alert['_id'])) {
                $alert_id = $alert['_id'];
            } else {
                $alert_id = "ID NOT FOUND";
                print_r($alert);
            }

            $alert_timestamp = Carbon::createFromTimestamp($alert['timestamp'] / 1000)->toAtomString();
            
            $alerts[] = [
                'alert_timestamp'       => $alert_timestamp,
                'alert_id'              => $alert_id,
                'alert_desc'            => $alert['description'],
                'alert_title'           => $alert['title'],
                'targeted_user'         => $alert['entities'][2]['id'],
                'targeted_service'      => $alert['entities'][3]['label'],
                'origin_type'           => $alert['entities'][4]['type'],
                'origin_data'           => $alert['entities'][4]['label'],
                'origin_location_type'  => $alert['entities'][5]['type'],
                'origin_location'       => $alert['entities'][5]['label']
            ];
        }

        echo '[+] alert count: '.count($alerts).PHP_EOL;

        file_put_contents(storage_path('app/collections/cas_alerts_high.json'), \Metaclassing\Utility::encodeJson($alerts));


        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
            'Content-Type: application/json',
            'Cache-Control: no-cache',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($alerts as $alert) {
            // add upsert datetime
            $alert['UpsertDate'] = Carbon::now()->toAtomString();

            $url = 'http://10.243.36.53:9200/cas_alerts_high/cas_alerts_high/'.$alert['alert_id'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $alert,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('CAS high alert was successfully inserted into ES: '.$alert['alert_id']);
            } else {
                Log::error('Something went wrong inserting CAS high alert: '.$alert['alert_id']);
                die('Something went wrong inserting CAS high alert: '.$alert['alert_id'].PHP_EOL);
            }
        }


        /*
        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        // cycle through Cylance devices
        foreach ($high_alerts as $alert) {
            // add upsert datetime
            $alert['UpsertDate'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'cas_high_alerts',
                    'value' => \Metaclassing\Utility::encodeJson($alert),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$alert['_id']);
            }
        }
        */
    }
}
