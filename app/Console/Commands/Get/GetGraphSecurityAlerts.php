<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetGraphSecurityAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:graphsecurityalerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get security alerts from the Microsoft Graph Security API';

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
        Log::info('[GetGraphSecurityAlerts.php] Starting MS Graph Security Alerts API Poll!');

        // setup cookiejar
        $cookiejar = storage_path('app/cookies/mgsa_cookie.txt');

        // get values from environment file
        $authorize_endpoint = getenv('MS_OAUTH_AUTHORIZE_ENDPOINT');
        $token_endpoint = getenv('MS_OAUTH_TOKEN_ENDPOINT');
        $token_endpoint2 = getenv('MS_OAUTH_TOKEN_ENDPOINT2');
        $client_id = getenv('SPECTRE_CLIENT_ID');
        $client_secret = getenv('SPECTRE_CLIENT_SECRET');
        $redirect_uri = getenv('SPECTRE_REDIRECT_URI');
        $code = getenv('SPECTRE_AUTH_CODE');

        // get stored refresh token from last run
        $refresh_token = file_get_contents(storage_path('app/responses/mgsa_refresh_token.txt'));

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup post data
        Log::info('[GetGraphSecurityAlerts.php] setting up post data for new refresh token...');
        $post_data = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            //'grant_type'    => 'refresh_token',
            'grant_type'    => 'client_credentials',
            //'scope'         => 'securityevents.read.all',
            'scope'         => 'https://graph.microsoft.com/.default',
            'refresh_token' => $refresh_token,
            'redirect_uri'  => $redirect_uri,
        ];
        $post_data = $this->postArrayToString($post_data);

        // setup headers and apply to crawler
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // post for access and refresh tokens and dump to file
        Log::info('[GetGraphSecurityAlerts.php] posting for new access and refresh tokens...');
        $json_response = $crawler->post($token_endpoint, '', $post_data);
        file_put_contents(storage_path('app/responses/mgsa_refresh_token_response.json'), $json_response);

        try {
            // get access token from response
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $access_token = $response['access_token'];
            Log::info('[GetGraphSecurityAlerts.php] got access token...');

            // get refresh token from response and dump to file
            if (array_key_exists('refresh_token', $response)) {
                $refresh_token = $response['refresh_token'];
                Log::info('[GetGraphSecurityAlerts.php] writing new refresh token to file...');
                file_put_contents(storage_path('app/responses/mgsa_refresh_token.txt'), $refresh_token);
            }
        } catch (\Exception $e) {
            Log::error('[GetGraphSecurityAlerts.php] ERROR failed to get access token: '.$e->getMessage());
            die('[GetGraphSecurityAlerts.php] ERROR failed to get access token: '.$e->getMessage().PHP_EOL);
        }

        // instantiate new crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth headers and apply to crawler
        $headers = [
            'Authorization: Bearer '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // setup graph security alerts url
        //$mgsa_url = 'https://graph.microsoft.com/v1.0/security/alerts';
        $mgsa_url = 'https://graph.microsoft.com/beta/security/alerts';

        // instantiate collection array and page count
        $collection = [];
        $page_count = 1;

        Log::info('[GetGraphSecurityAlerts.php] querying MS graph security api for list of alerts...');

        // query MS graph security API for alerts, using the value of nextLink to determine whether we loop or terminate
        do {
            // capture JSON response and dump to file
            $json_response = $crawler->get($mgsa_url);
            file_put_contents(storage_path('app/responses/mgsa_response.json'), $json_response);

            try {
                // attempt to JSON decode response
                $response = \Metaclassing\Utility::decodeJson($json_response);

                // add response values to collection and log alert count for current page
                $collection[] = $response['value'];
                Log::info('[GetGraphSecurityAlerts.php] received '.count($response['value']).' MS Graph Security alerts from page '.$page_count);
            } catch (\Exception $e) {
                Log::error('[GetGraphSecurityAlerts.php] ERROR failed to decode JSON response: '.$e->getMessage());
                die('[GetGraphSecurityAlerts.php] ERROR failed to decode JSON response: '.$e->getMessage().PHP_EOL);
            }

            // check for next link and set accordingly
            if (array_key_exists('@odata.nextLink', $response)) {
                $mgsa_url = $response['@odata.nextLink'];
                $page_count++;
            } else {
                $mgsa_url = null;
            }
        } while ($mgsa_url);

        // collapse collection down to simple array
        $mgsa_alerts_raw = array_collapse($collection);

        // final collection array
        $mgsa_alerts = [];

        foreach ($mgsa_alerts_raw as $alert) {
            // pull id and add back as mgsa_id
            $alert_id = array_pull($alert, 'id');
            $alert['mgsa_id'] = $alert_id;

            // add alert to mgsa alerts array
            $mgsa_alerts[] = $alert;
        }

        // log total count of collected graph security alerts and dump alerts to file
        Log::info('[GetGraphSecurityAlerts.php] collected '.count($mgsa_alerts).' MS graph security alerts');
        file_put_contents(storage_path('app/collections/mgsa_alerts.json'), \Metaclassing\Utility::encodeJson($mgsa_alerts));

        Log::info('[GetGraphSecurityAlerts.php] sending '.count($mgsa_alerts).' graph security alerts to kafka...');

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        // cycle through Cylance devices
        foreach ($mgsa_alerts as $alert) {
            // add upsert datetime
            $alert['upsertDate'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'ms-graph-security-api',
                    'value' => \Metaclassing\Utility::encodeJson($alert),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[GetGraphSecurityAlerts.php] ERROR sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            }
        }

        Log::info('[GetGraphSecurityAlerts.php] MS Graph Security Alerts completed!'.PHP_EOL);
    }

    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];
        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.(string) $value;
        }

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }
}
