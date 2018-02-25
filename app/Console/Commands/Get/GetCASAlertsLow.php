<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCASAlertsLow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:casalertslow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query Microsoft cloud app security for low alerts';

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
        Log::info(PHP_EOL.PHP_EOL.'**********************************'.PHP_EOL.'* Starting CAS Low Alerts Query! *'.PHP_EOL.'**********************************');

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
                    'eq'    => [0],
                ],
            ],
        ];
        $post_data_json = \Metaclassing\Utility::encodeJson($post_data);

        $low_alerts = [];
        $count = 0;

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
                $low_alerts[] = $data['data'];

                // set total
                $total = $data['total'];

                // add count of returned alerts to count
                $count += count($low_alerts);
            } else {
                // if no then M$ is probably throttling us, so sleep it off
                sleep(5);
            }
        } while ($count < $total);

        $low_alerts = array_collapse($low_alerts);

        // dump alerts collection to file
        file_put_contents(storage_path('app/responses/cas_alerts_low_collection.json'), \Metaclassing\Utility::encodeJson($low_alerts));

        $alerts = [];

        // cycle through alerts
        foreach ($low_alerts as $alert) {
            // pull _id from the alert and add it back as just id
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
        file_put_contents(storage_path('app/collections/cas_alerts_low.json'), \Metaclassing\Utility::encodeJson($alerts));

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        Log::info('[+] sending ['.count($alerts).'] CAS low alerts to Kafka...');

        // cycle through Cylance devices
        foreach ($alerts as $alert) {
            // add upsert datetime
            $alert['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'cas_low_alerts',
                    'value' => \Metaclassing\Utility::encodeJson($alert),
                ],
            ]);

            // check for and log errors
            if (isset($result[0]) && $result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending CAS low alert to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                //Log::info('[+] CAS low alert successfully sent to Kafka: '.$alert['alert_id']);
            }
        }

        Log::info('* CAS low alerts completed! *'.PHP_EOL);
    }
}
