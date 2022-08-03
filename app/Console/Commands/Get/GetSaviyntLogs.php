<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSaviyntLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:saviyntlogs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get logs from Saviynt';

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
        Log::info('[GetSaviyntLogs.php] Starting Poll');

        // get all the secret stuff
        $username = getenv('SAVIYNT_USERNAME');
        $password = getenv('SAVIYNT_PASSWORD');
        $login_uri = getenv('SAVIYNT_LOGIN_URI');
        $logs_uri = getenv('SAVIYNT_LOGS_URI');
        $webhook_uri = getenv('WEBHOOK_URI');

        // create date string for output filename
        $output_date = Carbon::now()->toDateString();

        // setup cookiejar for crawler
        $cookiejar = storage_path('app/cookies/saviynt_api.txt');
        
        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);
        
        // http headers
        $headers = [
            'Content-Type: application/json'
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);
        
        // build auth post body and JSON encode
        $auth_body = [
            'username'  => $username,
            'password'  => $password
        ];
        $auth_body_json = \Metaclassing\Utility::encodeJson($auth_body);

        try {
            // attempt to post auth request and capture response
            $json_response = $crawler->post($login_uri, '', $auth_body_json);
            file_put_contents(storage_path('app/responses/saviynt_auth.response'), $json_response);

            // attempt to JSON decode the response
            $response = \Metaclassing\Utility::decodeJson($json_response);

        } catch (\Exception $e) {
            $response = null;
            Log::error('[GetSaviyntLogs.php] '.$e->getMessage());
        }

        if ($response) {
            // check for access token key in response array
            if (array_key_exists('access_token', $response)) {
                // get access token
                $access_token = $response['access_token'];
                Log::info('[GetSaviyntLogs.php] got access token');
            } else {
                Log::error('[GetSaviyntLogs.php] no access token found in response');
                die('[GetSaviyntLogs.php] no access token found in response');
            }

            // build auth header and set to crawler
            $headers = [
                'Authorization: Bearer '.$access_token,
                'Content-Type: application/json'
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // build post body and JSON encode
            $post_body = [
                'analyticsname' => 'SIEM Logging2',
                'attributes'     => [
                    'timeFrame' => 30
                ]
            ];
            $post_body_json = \Metaclassing\Utility::encodeJson($post_body);

            try {
                // attempt to get logs from Saviynt API
                $json_response = $crawler->post($logs_uri, '', $post_body_json);
                file_put_contents(storage_path('app/responses/saviynt_logs.response'), $json_response);

                // attempt to JSON decode the response
                $response = \Metaclassing\Utility::decodeJson($json_response);

            } catch (\Exception $e) {
                $response = null;
                Log::error('[GetSaviyntLogs.php] '.$e->getMessage());
            }

            if ($response) {

                // get results from response
                $results = $response['results'];

                foreach ($results as $result) {
                    $object_name = null;
                    $data = null;
                    $subject = null;

                    try {
                        // attempt to JSON decode the Message field
                        $result_message = \Metaclassing\Utility::decodeJson($result['Message']);

                        // check if result message is an array
                        if (is_array($result_message)) {

                            // check if these field exist before trying to set them
                            if (array_has($result_message, 'data')) {
                                $data = $result_message['data'];
                            }

                            if (array_has($result_message, 'objectName')) {
                                $object_name = $result_message['objectName'];
                            }

                            if (array_has($result_message, 'message')) {
                                $subject = $result_message['message'];
                            }
                        }
                    } catch (\Exception $e) {
                        // if the JSON decode error's then catch it here and just assign Message to subject
                        $subject = $result['Message'];
                    }

                    // build event object
                    $event_obj = [
                        'timestamp.iso1806'         => $result['Event Time'],
                        'login'                     => $result['Accessed By'],
                        'sip'                       => $result['IP Address'],
                        'action'                    => $result['Action Taken'],
                        'objecttype'                => $result['Object Type'],
                        'objectname'                => $object_name,
                        'vendorinfo'                => $data,
                        'subject'                   => $subject,
                        'whsdp'                     => True,
                        'fullyqualifiedbeatname'    => 'webhookbeat-saviynt',
                        'original_message'          => \Metaclassing\Utility::encodeJson($result)
                    ];

                    // JSON encode event
                    $event_obj_json = \Metaclassing\Utility::encodeJson($event_obj);

                    file_put_contents(storage_path('app/output/saviynt/'.$output_date.'.log'), $event_obj_json, FILE_APPEND);

                    // post to webhook
                    $webhook_response = $crawler->post($webhook_uri, '', $event_obj_json);
                    file_put_contents(storage_path('app/responses/saviynt_webhook.response'), $webhook_response);
                }
            }

        }

        Log::info('[GetSaviyntLogs.php] Finished');

    }
}
