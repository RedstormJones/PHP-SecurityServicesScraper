<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCASAlertsMedium extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:casalertsmedium';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query Microsoft cloud app security for medium alerts';

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
        Log::info(PHP_EOL.PHP_EOL.'*************************************'.PHP_EOL.'* Starting CAS Medium Alerts Query! *'.PHP_EOL.'*************************************');

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

        $has_next = true;
        $medium_alerts = [];
        $count = 0;
        $total = 0;

        $current_timestamp = Carbon::now()->timestamp;

        $now_minus_10 = Carbon::now()->subMinutes(10);
        $alert_threshold = $now_minus_10->timestamp;
        $alert_threshold_ms = $alert_threshold * 1000;

        Log::info('[+] current timestamp: '.$current_timestamp);
        Log::info('[+] alert datetime threshold (ms): '.$alert_threshold_ms);

        do {
            // setup post data
            $post_data = [
                'filters'   => [
                    'severity' => [
                        'eq'    => 1,
                    ],
                    'date'  => [
                        'gte'   => $alert_threshold_ms,
                    ],
                ],
                'skip'  => $count,
            ];
            $post_data_json = \Metaclassing\Utility::encodeJson($post_data);

            // post to CAS endpoint and capture reponse
            $response = $crawler->post($cas_url, '', $post_data_json);

            // dump response to file
            file_put_contents(storage_path('app/responses/cas_response.json'), $response);

            // JSON decode response
            $data = \Metaclassing\Utility::decodeJson($response);

            // check if the key data exists
            if (array_key_exists('data', $data)) {
                // if yes the get the alerts
                $medium_alerts[] = $data['data'];

                // set total
                $total = $data['total'];

                // set has_next
                $has_next = $data['hasNext'];

                // add count of returned alerts to count
                $count += count($data['data']);
            } 
            /*
            else {
                // if no then M$ is probably throttling us, so sleep it off
                Log::warning('[WARN]: [CAS_ALERTS_MEDIUM] no data found in response: '.$data['detail']);

                $throttle_regex = '/Request was throttled\. Expected available in (\d+\.\d+) second[s]*\./';
                preg_match($throttle_regex, $data['detail'], $hits);

                Log::info('[+] [CAS_ALERTS_MEDIUM] sleeping it off ('.$hits[1].' sec)...');
                sleep((float) $hits[1]);
            }
            /**/
        } while ($has_next);

        $medium_alerts = array_collapse($medium_alerts);

        // dump alerts collection to file
        file_put_contents(storage_path('app/responses/cas_alerts_medium_collection.json'), \Metaclassing\Utility::encodeJson($medium_alerts));

        $alerts = [];

        // cycle through alerts
        foreach ($medium_alerts as $alert) {
            // downstream processing will throw errors if data already has an _id key,
            // so we need to pull _id from the alert and add it back as alert_id
            $id = array_pull($alert, '_id');
            $alert['alert_id'] = $id;

            // pull timestamp from alert and convert to datetime, then add it back as alert_timestamp
            $millisecond_timestamp = array_pull($alert, 'timestamp');
            $alert_timestamp = Carbon::createFromTimestamp($millisecond_timestamp / 1000);
            //$alert_timestamp->timezone = 'America/Chicago';
            $alert_timestamp = $alert_timestamp->toDateTimeString();
            $alert['alert_timestamp'] = $alert_timestamp;

            // pull the entities array from the alert
            $entities = array_pull($alert, 'entities');

            $ent_count = 1;
            $entity_array = [];

            // cycle through entities
            foreach ($entities as $entity) {
                // downstream processing will throw errors if data already has an id or type key, so pull id and type
                $entity_id = array_pull($entity, 'id');
                $entity_type = array_pull($entity, 'type');

                // add id back as <type>_id
                $entity[$entity_type.'_id'] = $entity_id;

                // check for an entityType key and remove it
                if (array_key_exists('entityType', $entity)) {
                    array_forget($entity, 'entityType');
                }

                // add entity to entity array using the entity type as the key
                $entity_array[$entity_type] = $entity;

                // increment entity count
                $ent_count++;
            }

            // add the entities array back to the alert
            $alert['entities'] = $entity_array;

            // build alerts collection
            $alerts[] = $alert;
        }

        // JSON encode and dump alerts to file
        file_put_contents(storage_path('app/collections/cas_alerts_medium.json'), \Metaclassing\Utility::encodeJson($alerts));

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        Log::info('[+] [CAS_ALERTS_MEDIUM] sending ['.count($alerts).'] CAS medium alerts to Kafka...');

        // cycle through Cylance devices
        foreach ($alerts as $alert) {
            // add upsert datetime
            $alert['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'cas_medium_alerts',
                    'value' => \Metaclassing\Utility::encodeJson($alert),
                ],
            ]);

            // check for and log errors
            if (isset($result[0]) && $result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] [CAS_ALERTS_MEDIUM] Error sending CAS medium alert to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                //Log::info('[+] CAS medium alert successfully sent to Kafka: '.$alert['alert_id']);
            }
        }

        Log::info('* [CAS_ALERTS_MEDIUM] CAS medium alerts completed! *'.PHP_EOL);
    }
}
