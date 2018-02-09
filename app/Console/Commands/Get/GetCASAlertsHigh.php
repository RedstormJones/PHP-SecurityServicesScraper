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
        Log::info(PHP_EOL.PHP_EOL.'***********************************'.PHP_EOL.'* Starting CAS High Alerts Query! *'.PHP_EOL.'***********************************');

        // setup file to hold cookie
        $cookiejar = storage_path('app/cookies/cas_cookie.txt');

        // create crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // get CAS token and url from environment file
        $cas_token = getenv('CAS_TOKEN');
        $cas_url = getenv('CAS_URL').'/api/v1/alerts/';

        // setup authorization header and apply to crawler object
        $headers = [
            'Authorization: Token '.$cas_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // setup post data
        $post_data = [
            'filters'   => [
                'severity' => [
                    'eq'    => [2],
                ],
            ],
        ];

        $alerts = [];
        $count = 0;

        do {
            $response = $crawler->post($cas_url, '', \Metaclassing\Utility::encodeJson($post_data));
            file_put_contents(storage_path('app/responses/cas_alerts_high.json'), $response);

            $data = \Metaclassing\Utility::decodeJson($response);
            $high_alerts = $data['data'];

            // add count of returned alerts to count
            $count += count($high_alerts);

            // set total
            $total = $data['total'];

            // cycle through alerts
            foreach ($high_alerts as $alert) {
                // pull _id from the alert and add it back as just id
                $id = array_pull($alert, '_id');
                $alert['alert_id'] = $id;

                // convert millisecond timestamp to datetime
                $alert_timestamp = Carbon::createFromTimestamp($alert['timestamp'] / 1000)->toAtomString();
                $alert['alert_timestamp'] = $alert_timestamp;

                $entities = array_pull($alert, 'entities');
                $ent_count = 1;
                $entity_array = [];
                foreach ($entities as $entity) {
                    $entity_id = $entity['id'];
                    $filtered_entity = array_except($entity, ['id']);

                    $filtered_entity['id_'.$ent_count] = $entity_id;
                    $ent_count++;

                    $entity_array[] = $filtered_entity;
                }

                $alert_no_entities = array_forget($alert, ['entities']);
                $alert['entities'] = $entity_array;

                // build alerts collection
                $alerts[] = $alert;
            }
        } while ($count < $total);

        // JSON encode and dump alerts to file
        file_put_contents(storage_path('app/collections/cas_alerts_high.json'), \Metaclassing\Utility::encodeJson($alerts));

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        // cycle through Cylance devices
        foreach ($alerts as $alert) {
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
                Log::info('[*] Data successfully sent to Kafka: '.$alert['alert_id']);
            }
        }

        /*
        // new crawler object
        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup headers and apply to crawler object
        $headers = [
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
            'Content-Type: application/json',
            'Cache-Control: no-cache',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // cycle through alerts collection
        foreach ($alerts as $alert) {
            // add upsert datetime
            $alert['UpsertDate'] = Carbon::now()->toAtomString();

            // setup elastic url
            $url = 'http://10.243.36.53:9200/cas_alerts_high/cas_alerts_high/'.$alert['_id'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            // setup post data
            $post = [
                'doc'           => $alert,
                'doc_as_upsert' => true,
            ];

            // post upsert to elastic and capture response
            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            // JSON decode response and check for errors
            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('CAS high alert was successfully inserted into ES: '.$alert['_id']);
            } else {
                Log::error('Something went wrong inserting CAS high alert: '.$alert['_id']);
                die('Something went wrong inserting CAS high alert: '.$alert['_id'].PHP_EOL);
            }
        }
        */
    }
}
