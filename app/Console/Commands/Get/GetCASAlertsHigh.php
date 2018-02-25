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
        $post_data_json = \Metaclassing\Utility::encodeJson($post_data);

        $high_alerts = [];
        $count = 0;
        $total = 0;

        do {
            // post to CAS endpoint and capture reponse
            $response = $crawler->post($cas_url, '', $post_data_json);

            // dump response to file
            file_put_contents(storage_path('app/responses/cas_response.json'), $response);

            // JSON decode response
            $data = \Metaclassing\Utility::decodeJson($response);

            // check if the key data exists
            if (array_key_exists('data', $data)) {
                // if yes the get the alerts
                $high_alerts[] = $data['data'];

                // set total
                $total = $data['total'];

                // add count of returned alerts to count
                $count += count($high_alerts);
            } else {
                // if no then M$ is probably throttling us, so sleep it off
                Log::warning('[WARN] no data found in response: '.$response);

                $throttle_regex = '/Request was throttled\. Expected available in (\d\.\d) second[s]*\./';
                preg_match($throttle_regex, $data['detail'], $hits);

                Log::info('[+] sleeping it off ('.$hits[1].' sec)...');
                sleep((float) $hits[1]);
            }
        } while ($count < $total);

        $high_alerts = array_collapse($high_alerts);

        // dump alerts collection to file
        file_put_contents(storage_path('app/responses/cas_alerts_high_collection.json'), \Metaclassing\Utility::encodeJson($high_alerts));

        $alerts = [];

        // cycle through alerts
        foreach ($high_alerts as $alert) {
            // downstream processing will throw errors if data already has an _id key,
            // so we need to pull _id from the alert and add it back as alert_id
            $id = array_pull($alert, '_id');
            $alert['alert_id'] = $id;

            // pull timestamp from alert and convert to datetime, then add it back as alert_timestamp
            $millisecond_timestamp = array_pull($alert, 'timestamp');
            $alert_timestamp = Carbon::createFromTimestamp($millisecond_timestamp / 1000)->toAtomString();
            $alert['alert_timestamp'] = $alert_timestamp;

            // pull the entities array from the alert
            $entities = array_pull($alert, 'entities');

            $ent_count = 1;
            $entity_array = [];

            // cycle through entities
            foreach ($entities as $entity) {
                // downstream processing will throw errors if data already has an id or type key,
                // so pull id and type, and add them back as entity_id and entity_type
                $entity_id = array_pull($entity, 'id');
                $entity_type = array_pull($entity, 'type');

                $entity['entity'.$ent_count.'_id'] = $entity_id;
                $entity['entity'.$ent_count.'_type'] = $entity_type;

                // check for an entityType key and remove it
                if (array_key_exists('entityType', $entity)) {
                    array_forget($entity, 'entityType');
                }

                // add entity to entity array
                $entity_array['entity'.$ent_count] = $entity;

                // increment entity count
                $ent_count++;
            }

            // add the entities array back to the alert
            $alert['entities'] = $entity_array;

            // build alerts collection
            $alerts[] = $alert;
        }

        // JSON encode and dump alerts to file
        file_put_contents(storage_path('app/collections/cas_alerts_high.json'), \Metaclassing\Utility::encodeJson($alerts));

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        Log::info('[+] sending ['.count($alerts).'] CAS high alerts to Kafka...');

        // cycle through Cylance devices
        foreach ($alerts as $alert) {
            // add upsert datetime
            $alert['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'cas_high_alerts',
                    'value' => \Metaclassing\Utility::encodeJson($alert),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending CAS high alert to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                //Log::info('[+] CAS high alert successfully sent to Kafka: '.$alert['alert_id']);
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

        Log::info('* CAS high alerts completed! *'.PHP_EOL);
    }
}
