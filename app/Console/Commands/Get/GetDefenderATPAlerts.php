<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetDefenderATPAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:defenderatpalerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get alerts from the Windows Defender ATP REST API';

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
        Log::info(PHP_EOL.PHP_EOL.'*****************************************'.PHP_EOL.'* Starting Defender ATP Alerts command! *'.PHP_EOL.'*****************************************');

        $cookiejar = storage_path('app/cookies/atp_cookie.txt');

        // get values from environment file
        $token_endpoint = getenv('MS_OAUTH_TOKEN_ENDPOINT');
        $app_id = getenv('MS_APP_ID');
        $app_key = getenv('MS_APP_KEY');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup access token post data
        Log::info('[+] setting up post data...');
        $post_data = 'resource=https://graph.windows.net&client_id='.$app_id.'&client_secret='.$app_key.'&grant_type=client_credentials';

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // post to token endpoint
        Log::info('[+] posting for access token...');
        $json_response = $crawler->post($token_endpoint, '', $post_data);
        file_put_contents(storage_path('app/responses/token_response.json'), $json_response);

        try {
            // get access token from response
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $access_token = $response['access_token'];
            Log::info('[+] got access token...');
        } catch (\Exception $e) {
            Log::error('[!] failed to get access token: '.$e->getMessage());
            die('[!] failed to get access token: '.$e->getMessage().PHP_EOL);
        }

        // instantiate new crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth headers and apply to crawler
        $headers = [
            'Authorization: Bearer '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // try to hit the defender ATP alerts endpoint
        $defender_api_url = getenv('DEFENDER_ALERTS_ENDPOINT').'?ago=PT12H';

        // capture response and dump to file
        Log::info('[+] requesting Defender ATP alerts for past 12 hours: '.$defender_api_url);
        $json_response = $crawler->get($defender_api_url);
        file_put_contents(storage_path('app/responses/defender_alerts_response.json'), $json_response);

        // attempt to decode JSON response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info('[+] received ['.count($response).'] alerts from Defender ATP');
        } catch (\Exception $e) {
            Log::error('[!] failed to decode JSON response: '.$e->getMessage());
            die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }

        if (count($response)) {
            $defender_alerts = [];

            // convert IP strings into arrays
            foreach ($response as $alert) {
                // IPv4 addresses
                $ipv4_list = array_pull($alert, 'InternalIPv4List');
                $ipv4_pieces = explode(';', $ipv4_list);
                $ipv4_array = [];
                foreach ($ipv4_pieces as $ip) {
                    $ipv4_array[] = $ip;
                }
                $alert['InternalIPv4List'] = $ipv4_array;

                // IPv6 addresses
                $ipv6_list = array_pull($alert, 'InternalIPv6List');
                $ipv6_pieces = explode(';', $ipv6_list);
                $ipv6_array = [];
                foreach ($ipv6_pieces as $ip) {
                    $ipv6_array[] = $ip;
                }
                $alert['InternalIPv6List'] = $ipv6_array;

                // build defender alerts array
                $defender_alerts[] = $alert;
            }

            // dump alert collection to file
            file_put_contents(storage_path('app/collections/defender-atp-alerts.json'), \Metaclassing\Utility::encodeJson($defender_alerts));

            Log::info('[+] sending ['.count($response).'] Defender ATP alerts to kafka...');

            // instantiate a Kafka producer config and set the broker IP
            $config = \Kafka\ProducerConfig::getInstance();
            $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

            // instantiate new Kafka producer
            $producer = new \Kafka\Producer();

            // cycle through Cylance devices
            foreach ($response as $alert) {
                // add upsert datetime
                $alert['UpsertDate'] = Carbon::now()->toAtomString();

                // ship data to Kafka
                $result = $producer->send([
                    [
                        'topic' => 'defender-atp',
                        'value' => \Metaclassing\Utility::encodeJson($alert),
                    ],
                ]);

                // check for and log errors
                if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                    Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
                } else {
                    Log::info('[*] Sent Defender ATP alert '.$alert['AlertId']);
                }
            }
        }

        Log::info('[***] Defender ATP Alerts command completed! [***]'.PHP_EOL);
    }
}
