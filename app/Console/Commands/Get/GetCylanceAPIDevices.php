<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCylanceAPIDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:cylanceapidevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get devices from Cylance API';

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
        Log::info(PHP_EOL.PHP_EOL.'****************************************'.PHP_EOL.'* Starting Cylance API devices client! *'.PHP_EOL.'****************************************');

        // authenticate to the Cylance API and get JWT access token
        $access_token = $this->generateAccessToken();

        // instantiate crawler
        $cookiejar = storage_path('app/cookies/cylanceAPI.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup headers
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // set paging values
        $page_num = 1;
        $page_size = 200;

        // collection array
        $collection = [];

        /*
         *
         *  GET ALL DEVICES
         *
         */
        Log::info('[+] querying Cylance API for all devices...');
        do {
            // loop to get all devices while page_num is lte to total_pages
            $url = getenv('CYLANCE_ENDPOINT').'/devices/v2?page='.$page_num.'&page_size='.$page_size;
            //Log::info('[+] querying Cylance device endpoint: '.$url);

            // capture response and dump to file
            $json_response = $crawler->get($url);
            file_put_contents(storage_path('app/responses/cylanceapi-devices.json'), $json_response);

            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);

            // add devices to collection array
            $collection[] = $response['page_items'];

            if (array_key_exists('total_pages', $response)) {
                // set total_pages and increment page_num
                $total_pages = $response['total_pages'];
            }

            $page_num++;
        } while ($page_num <= $total_pages);

        // collapse collection array down to simple array and dump to file
        $devices = array_collapse($collection);
        file_put_contents(storage_path('app/collections/cylanceapi-devices.json'), \Metaclassing\Utility::encodeJson($devices));

        Log::info('[+] ...DONE');

        /*
          *
          *  GET ALL DEVICE DETAILS
          *
          */
        Log::info('[+] querying Cylance API for device details...');

        // device details array
        $device_details = [];

        foreach ($devices as $device) {
            // setup device details endpoint
            $url = getenv('CYLANCE_ENDPOINT').'/devices/v2/'.$device['id'];
            //Log::info('[+] querying Cylance device details endpoint: '.$url);

            // capture response and dump to file
            $json_response = $crawler->get($url);
            file_put_contents(storage_path('app/responses/device-details.json'), $json_response);

            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);

            // add device details to device details array
            $device_details[] = $response;
        }

        // JSON encode and dump device details to file
        file_put_contents(storage_path('app/collections/cylanceapi-device-details.json'), \Metaclassing\Utility::encodeJson($device_details));

        Log::info('[+] ...DONE');

        Log::info('[+] querying Cylance API for device threats and corresponding threat details...');

        // instantiate devices and threats array
        $devices_and_threats = [];

        // cycle through devices and get threats for each device
        foreach ($device_details as $device) {
            $page_num = 1;
            $page_size = 200;

            $collection = [];

            /*
              *
              *  GET DEVICE THREATS
              *
              */
            do {
                // setup device threats url
                $url = getenv('CYLANCE_ENDPOINT').'/devices/v2/'.$device['id'].'/threats?page='.$page_num.'&page_size='.$page_size;
                //Log::info('[+] querying Cylance device threats endpoint: '.$url);

                // send request, capture response and dump to file
                $json_response = $crawler->get($url);
                file_put_contents(storage_path('app/responses/device-threats.json'), $json_response);

                try {
                    // attempt to JSON decode response
                    $response = \Metaclassing\Utility::decodeJson($json_response);

                    // add device threats to collection
                    $collection[] = $response['page_items'];
                } catch (\Exception $e) {
                    // an exception will be thrown if the JSON response is empty, so catch it and carry on
                    Log::error('[!] JSON response for device threats was empty: '.$e->getMessage());
                }

                if (array_key_exists('total_pages', $response)) {
                    // set total_pages and increment page_num
                    $total_pages = $response['total_pages'];
                }

                $page_num++;
            } while ($page_num <= $total_pages);

            // collapse device threats array and dump to file
            $device_threats = array_collapse($collection);
            file_put_contents(storage_path('app/responses/device-threats.json'), \Metaclassing\Utility::encodeJson($device_threats));

            /*
              *
              *  GET THREAT DETAILS
              *
              */
            $device_threat_details = [];

            foreach ($device_threats as $threat) {
                // setup threat details url
                $url = getenv('CYLANCE_ENDPOINT').'/threats/v2/'.$threat['sha256'];
                //Log::info('[+] querying Cylance threats endpoint: '.$url);

                // send request, capture response and dump to file
                $json_response = $crawler->get($url);
                file_put_contents(storage_path('app/responses/device-threat.json'), $json_response);

                // check if we got something
                if ($json_response) {
                    // if yes, JSON decode and add to device_threat_details
                    $response = \Metaclassing\Utility::decodeJson($json_response);

                    // add threat info to device threat details array
                    $device_threat_details[] = [
                        'name'                  => $response['name'],
                        'date_found'            => $threat['date_found'],
                        'sha256'                => $response['sha256'],
                        'file_path'             => $threat['file_path'],
                        'file_status'           => $threat['file_status'],
                        'cert_publisher'        => $response['cert_publisher'],
                        'cert_issuer'           => $response['cert_issuer'],
                        'cert_timestamp'        => $response['cert_timestamp'],
                        'safelisted'            => $response['safelisted'],
                        'signed'                => $response['signed'],
                        'file_size'             => $response['file_size'],
                        'global_quarantined'    => $response['global_quarantined'],
                        'av_industry'           => $response['av_industry'],
                        'classification'        => $response['classification'],
                        'sub_classification'    => $response['sub_classification'],
                        'auto_run'              => $response['auto_run'],
                        'detected_by'           => $response['detected_by'],
                        'cylance_score'         => $response['cylance_score'],
                        'md5'                   => $response['md5'],
                        'running'               => $response['running'],
                        'unique_to_cylance'     => $response['unique_to_cylance'],
                    ];
                } else {
                    // otherwise, no threats for this device
                    Log::info('[+] no threat details found for: '.$threat['sha256']);
                }
            }

            // dump device threat details to file
            file_put_contents(storage_path('app/collections/cylanceapi-device-threats.json'), \Metaclassing\Utility::encodeJson($device_threat_details));

            // if there are threats for this device then add an element to the array for each threat
            if (count($device_threat_details)) {
                foreach ($device_threat_details as $device_threat) {
                    $unique_id = $device['id'].'-'.$device_threat['md5'];

                    $devices_and_threats[] = [
                        'name'                  => $device['name'],
                        'state'                 => $device['state'],
                        'date_offline'          => $device['date_offline'],
                        'agent_version'         => $device['agent_version'],
                        'update_available'      => $device['update_available'],
                        'mac_addresses'         => $device['mac_addresses'],
                        'date_last_modified'    => $device['date_last_modified'],
                        'background_detection'  => $device['background_detection'],
                        'host_name'             => $device['host_name'],
                        'is_safe'               => $device['is_safe'],
                        'device_id'             => $device['id'],
                        'unique_id'             => $unique_id,
                        'policy'                => $device['policy'],
                        'last_logged_in_user'   => $device['last_logged_in_user'],
                        'ip_addresses'          => $device['ip_addresses'],
                        'os_version'            => $device['os_version'],
                        'date_first_registered' => $device['date_first_registered'],
                        'update_type'           => $device['update_type'],
                        'threat'                => $device_threat,
                    ];
                }
            } else {
                // otherwise just add the device with an empty threats array
                $devices_and_threats[] = [
                    'name'                  => $device['name'],
                    'state'                 => $device['state'],
                    'date_offline'          => $device['date_offline'],
                    'agent_version'         => $device['agent_version'],
                    'update_available'      => $device['update_available'],
                    'mac_addresses'         => $device['mac_addresses'],
                    'date_last_modified'    => $device['date_last_modified'],
                    'background_detection'  => $device['background_detection'],
                    'host_name'             => $device['host_name'],
                    'is_safe'               => $device['is_safe'],
                    'device_id'             => $device['id'],
                    'unique_id'             => $device['id'],
                    'policy'                => $device['policy'],
                    'last_logged_in_user'   => $device['last_logged_in_user'],
                    'ip_addresses'          => $device['ip_addresses'],
                    'os_version'            => $device['os_version'],
                    'date_first_registered' => $device['date_first_registered'],
                    'update_type'           => $device['update_type'],
                    'threat'                => null,
                ];
            }
        }

        // JSON encode and dump devices and threats array to file
        file_put_contents(storage_path('app/collections/devices-and-threats.json'), \Metaclassing\Utility::encodeJson($devices_and_threats));

        Log::info('[+] ...DONE');

        Log::info('[+] sending Cylance device logs to Kafka...');

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        // cycle through Cylance devices
        foreach ($devices_and_threats as $device) {
            // add upsert datetime
            $device['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'cylance',
                    'value' => \Metaclassing\Utility::encodeJson($device),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            }
        }

        Log::info('[+] ...DONE');

        Log::info('* Cylance API devices client completed! *'.PHP_EOL);
    }

    /**
     * Helper function to handle authentication to the Cylance API. Returns a JWT access token.
     *
     * @return mixed
     */
    public function generateAccessToken()
    {
        // weird timestamp stuff
        $timeout = 1800;
        $now = Carbon::now('UTC');
        $timeout_timestamp = Carbon::now('UTC')->addSeconds($timeout);
        $epoch_time = $now->diffInSeconds(Carbon::createFromTimestampUTC(0));
        $epoch_timeout = $timeout_timestamp->diffInSeconds(Carbon::createFromTimestampUTC(0));

        // random uuid
        $jti_val = file_get_contents('/proc/sys/kernel/random/uuid');

        // read in claim values from environment file
        $tid_val = getenv('CYLANCE_TENANT_ID');
        $app_id = getenv('CYLANCE_APP_ID');
        $app_secret = getenv('CYLANCE_APP_SECRET');

        // setup authentication endpoint
        $auth_url = getenv('CYLANCE_AUTH_URL');

        // build claims array and JSON encode
        $claims = [
            'sub'   => $app_id,
            'iss'   => 'http://cylance.com',
            'iat'   => $epoch_time,
            'exp'   => $epoch_timeout,
            'jti'   => $jti_val,
            'tid'   => $tid_val,
        ];
        $json_claims = \Metaclassing\Utility::encodeJson($claims);

        // JWT header
        $jwt_header = [
            'typ'   => 'JWT',
            'alg'   => 'HS256',
        ];
        $json_jwt_header = \Metaclassing\Utility::encodeJson($jwt_header);

        // base64 encode JWT header and JSON encoded claims
        $base64_jwt_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($json_jwt_header));
        $base64_claims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($json_claims));

        // generate signature and base64 encode
        $sig = hash_hmac('sha256', $base64_jwt_header.'.'.$base64_claims, $app_secret, true);
        $base64_sig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($sig));

        // build auth token
        $auth_token = $base64_jwt_header.'.'.$base64_claims.'.'.$base64_sig;

        // setup auth post data
        $post_data = [
            'auth_token'    => $auth_token,
        ];

        // instantiate new crawler
        $cookiejar = storage_path('app/cookies/cylance-auth.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // set headers
        $headers = [
            'Content-Type: application/json; charset=utf-8',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // post auth token to Cylance authentication endpoint and capture response
        $json_response = $crawler->post($auth_url, $auth_url, \Metaclassing\Utility::encodeJson($post_data));
        file_put_contents(storage_path('app/responses/cylance_api_auth.json'), $json_response);

        // JSON decode response
        $response = \Metaclassing\Utility::decodeJson($json_response);

        // get access token from response and return it
        if (array_key_exists('access_token', $response)) {
            return $response['access_token'];
        } else {
            // otherwise pop smoke and bail
            Log::error('[!] failed to get access token');
            die('[!] failed to get access token'.PHP_EOL);
        }
    }
}
