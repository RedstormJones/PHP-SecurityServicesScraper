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
        Log::info('[GetDefenderATPAlerts.php] Starting Defender ATP Alerts Poll!');

        $webhook_uri = getenv('WEBHOOK_URI');

        $cookiejar = storage_path('app/cookies/atp_cookie.txt');

        // set up output date for output file
        $output_date = Carbon::now()->toDateString();

        // get values from environment file
        $token_endpoint = getenv('MS_OAUTH_DEFENDER_TOKEN_ENDPOINT');
        $app_id = getenv('MS_APP_ID');
        $app_key = getenv('MS_APP_KEY');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup access token post data
        Log::info('[GetDefenderATPAlerts.php] setting up post data...');
        $post_data = 'resource=https://graph.windows.net&client_id='.$app_id.'&client_secret='.$app_key.'&grant_type=client_credentials';

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // post to token endpoint
        Log::info('[GetDefenderATPAlerts.php] posting for access token...');
        $json_response = $crawler->post($token_endpoint, '', $post_data);
        file_put_contents(storage_path('app/responses/token_response.json'), $json_response);

        try {
            // get access token from response
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $access_token = $response['access_token'];
            Log::info('[GetDefenderATPAlerts.php] got access token...');
        } catch (\Exception $e) {
            Log::error('[GetDefenderATPAlerts.php] ERROR: failed to get access token: '.$e->getMessage());
            die('[GetDefenderATPAlerts.php] ERROR: failed to get access token: '.$e->getMessage().PHP_EOL);
        }

        // instantiate new crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth headers and apply to crawler
        $headers = [
            'Authorization: Bearer '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // try to hit the defender ATP alerts endpoint
        $defender_api_url = getenv('DEFENDER_ALERTS_ENDPOINT').'?ago=PT1H';

        // capture response and dump to file
        Log::info('[GetDefenderATPAlerts.php] requesting Defender ATP alerts for past 1 hours: '.$defender_api_url);
        $json_response = $crawler->get($defender_api_url);
        file_put_contents(storage_path('app/responses/defender_alerts_response.json'), $json_response);

        // attempt to decode JSON response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info('[GetDefenderATPAlerts.php] received ['.count($response).'] alerts from Defender ATP');
        } catch (\Exception $e) {
            Log::error('[GetDefenderATPAlerts.php] ERROR: failed to decode JSON response: '.$e->getMessage());
            die('[GetDefenderATPAlerts.php] ERROR: failed to decode JSON response: '.$e->getMessage().PHP_EOL);
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

            // setup webhook cookie jar
            $cookiejar = storage_path('app/cookies/OCwebhook.cookie');

            // setup new crawler
            $crawler = new \Crawler\Crawler($cookiejar);

            // cycle through alerts and build new object array with fields mapped to the LR metadata schema
            foreach ($defender_alerts as $alert) {

                // immediately JSON encode alert and append it to the original output file before LR metadata transformations
                $alert_json = \Metaclassing\Utility::encodeJson($alert);
                file_put_contents(storage_path('app/output/defender/'.$output_date.'-defender-atp-alerts.log'), $alert_json."\n", FILE_APPEND);

                // metadata transformations to adhere to LR schema
                $lr_alert = [
                    'beatname'                  => 'webhookbeat',
                    'device_type'               => 'Windows_Defender',
                    'sname'                     => $alert['ComputerDnsName'],
                    'severity'                  => $alert['Severity'],
                    'objecttype'                => $alert['Category'],
                    'tag1'                      => $alert['Category'],
                    'subject'                   => $alert['LinkToWDATP'],
                    'vendorinfo'                => $alert['AlertTitle'],
                    'login'                     => $alert['Actor'],
                    'hash'                      => $alert['Sha1'],
                    'objectname'                => $alert['FileName'],
                    'sip'                       => $alert['IpAddress'],
                    'url'                       => $alert['Url'],
                    'account'                   => $alert['LogOnUsers'],
                    'object'                    => $alert['ThreatCategory'],
                    'threatname'                => $alert['ThreatName'],
                    'action'                    => $alert['RemediationAction'],
                    'result'                    => $alert['RemediationIsSuccess'],
                    'command'                   => $alert['CommandLine'],
                    'group'                     => $alert['MachineGroup'],
                    'threatid'                  => $alert['IocUniqueId'],
                    'reason'                    => $alert['CloudCreatedMachineTags'],
                    'whsdp'                     => True,
                    'fullyqualifiedbeatname'    => 'webhookbeat-defender',
                    'original_message'          => \Metaclassing\Utility::encodeJson($alert)
                ];

                // JSON encode alert
                $lr_alert_json = \Metaclassing\Utility::encodeJson($lr_alert);

                // post to webhookbeat, capture response and dump to file
                $webhook_response = $crawler->post($webhook_uri, '', $lr_alert_json);
                file_put_contents(storage_path('app/responses/defender_webhook.response'), $webhook_response);
            }

            // dump alert collection to file
            Log::info('[GetDefenderATPAlerts.php] sending '.count($defender_alerts).' Defender ATP alerts to file');
            file_put_contents(storage_path('app/collections/defender-atp-alerts.json'), \Metaclassing\Utility::encodeJson($defender_alerts));
        }

        Log::info('[GetDefenderATPAlerts.php] Defender ATP Alerts completed!');
    }
}
