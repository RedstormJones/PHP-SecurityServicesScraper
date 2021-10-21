<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetLastPassEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:lastpassevents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get events from the LastPass API for the past 10 minutes';

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
        Log::info('[GetLastPassEvents.php] Starting LastPass API events poll!');

        // setup date string for output filename
        $output_date = Carbon::now()->toDateString();

        $webhook_uri = getenv('WEBHOOK_URI');

        // setup from date for post body
        $from_date = Carbon::now('America/Chicago')->subMinutes(10)->toDateTimeString();
        $to_date = Carbon::now('America/Chicago')->toDateTimeString();
        Log::info('[GetLastPassEvents.php] querying lastpass for time period: '.$from_date.' - '.$to_date);

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie_clicksblocked.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup lastpass uri
        $lastpass_uri = 'https://lastpass.com/enterpriseapi.php';

        // get lastpass cid and provhash from environment file
        $lastpass_cid = getenv('LASTPASS_CID');
        $lastpass_prov_hash = getenv('LASTPASS_PROV_HASH');

        // build post body and JSON encode it
        $post_body = [
            'cid'       => $lastpass_cid,
            'apiuser'   => 'PHP_API',
            'provhash'  => $lastpass_prov_hash,
            'cmd'       => 'reporting',
            'data'      => [
                'from'  => $from_date,
                'to'    => $to_date
            ]
        ];
        $post_body_json = \Metaclassing\Utility::encodeJson($post_body);

        // build headers and set to crawler object
        $headers = [
            'Content-Type: application/json'
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        try {
            // post request and capture response and dump it to file
            $json_response = $crawler->post($lastpass_uri, '', $post_body_json);
            file_put_contents(storage_path('app/responses/lastpass.response'), $json_response);

            // attempt to JSON decode the response
            $response = \Metaclassing\Utility::decodeJson($json_response);

        } catch (\Exception $e) {
            $response = null;
            Log::error('[GetLastPassEvents.php] '.$e->getMessage());
        }

        // check that response is not null
        if ($response) {

            // check that the response status is OK
            if ($response['status'] == 'OK') {

                // get event data from the response
                $data = $response['data'];
                Log::info('[GetLastPassEvents.php] count of events received from LastPass for the last 10 minutes: '.count($data));

                // cycle through event data and build event objects
                foreach ($data as $event) {
                    $event_timestamp = str_replace(' ', 'T', $event['Time']).'Z';
                    
                    // build event object
                    $event_obj = [
                        'timestamp.iso1806'         => $event_timestamp,
                        'login'                     => $event['Username'],
                        'sip'                       => $event['IP_Address'],
                        'action'                    => $event['Action'],
                        'object'                    => $event['Data'],
                        'whsdp'                     => True,
                        'fullyqualifiedbeatname'    => 'LASTPASS_EVENT',
                        'original_message'          => \Metaclassing\Utility::encodeJson($event)
                    ];

                    $event_obj_json = \Metaclassing\Utility::encodeJson($event_obj)."\n";

                    // JSON encode event object and append it to the output file
                    //file_put_contents(storage_path('app/output/lastpass/'.$output_date.'-lastpass_events.log'), $event_obj_json, FILE_APPEND);

                    $webhook_response = $crawler->post($webhook_uri, '', $event_obj_json);
                    file_put_contents(storage_path('app/responses/webhook.response'), $webhook_response);
                }

            } else {
                Log::info('[GetLastPassEvents.php] response status is not OK: '.$response['status']);
            }
        } else {
            // otherwise pop smoke and bail
            Log::info('[GetLastPassEvents.php] LastPass API returned either no data or an error, check the logs...');
        }
    }
}
