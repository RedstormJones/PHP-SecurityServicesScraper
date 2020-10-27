<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetAkamaiThreatEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:threatevents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Akamai threat events';

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
        Log::info('[GetAkamaiThreatEvents.php] Starting Akamai Threat events API Poll!');

        // setup date string for output filename
        $output_date = Carbon::now()->toDateString();

        // setup timeframe (sec) for request argument
        $start_timeframe_secs = Carbon::now()->subMinutes(10)->timestamp;
        $end_timeframe_secs = Carbon::now()->timestamp;
        //Log::info('[GetAkamaiThreatEvents.php] startTimeSec: '.$start_timeframe_secs);
        //Log::info('[GetAkamaiThreatEvents.php] endTimeSec: '.$end_timeframe_secs);

        // config id
        $akamai_config_id = getenv('AKAMAI_CONFIG_ID');

        // setup variables for total and running counts and for page number
        $total_records = 0;
        $running_count = 0;
        $page_number = 1;

        do {
            // setup query and JSON encode it
            $query = [
                        'startTimeSec'  => $start_timeframe_secs,        
                        'endTimeSec'    => $end_timeframe_secs,
                        'orderBy'       => 'DESC',
                        'pageNumber'    => $page_number,
                        'pageSize'      => 1000,
                        'filters'       => new \stdClass(),
                    ];
            $query = json_encode($query);
            Log::info('[GetAkamaiThreatEvents.php] JSON encoded query: '.$query);

            // setup auth stuff and add query
            $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile('default', '.edgerc');        
            $auth->setHttpMethod('POST');
            $auth->setPath('/etp-report/v3/configs/'.$akamai_config_id.'/threat-events/details');
            $auth->setBody($query);

            // setup the context array
            $context = array(        
                'http' => array(
                    'ignore_errors' => TRUE,
                    'timeout'   => 1200,
                    'protocol_version'=> '1.1',
                    'method'    => 'POST',
                    'header'    => array(
                        'Authorization: ' . $auth->createAuthHeader(),
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($query),
                    ),
                    'content' => $query
                )
            );
            $context = stream_context_create($context);

            Log::info('[GetAkamaiThreatEvents.php] querying for page number '.$page_number);

            // send request and capture response, otherwise dump response to file
            $response = file_get_contents('https://'.$auth->getHost().$auth->getPath(), null, $context);
            file_put_contents(storage_path('app/responses/akamai-threat-events.response'), $response);

            // check if response is empty
            if ($response) {
                // JSON decode response
                $response = \Metaclassing\Utility::decodeJson($response);

                if (array_key_exists('dataRows', $response)) {
                    // get AUP events from response dataRows
                    $aup_events = $response['dataRows'];

                    // get total records count from response
                    $total_records = $response['pageInfo']['totalRecords'];

                    // increment running count by the number of events we got and log it
                    $running_count += count($aup_events);
                    Log::info('[GetAkamaiThreatEvents.php] current running count: '.$running_count);

                    // cycle through AUP events
                    foreach ($aup_events as $data) {
                        // JSON encode with newline and append to output file
                        $json_data = \Metaclassing\Utility::encodeJson($data)."\n";
                        file_put_contents(storage_path('app/output/akamai_threat_events/'.$output_date.'-akamai-threat-events.log'), $json_data, FILE_APPEND);
                    }

                    // increment page number for next request if needed
                    $page_number += 1;
                } else {
                    $json_response = \Metaclassing\Utility::encodeJson($response);
                    Log::error('[GetAkamaiThreatEvents.php] dataRows not found in response: '.$json_response);
                    die('[GetAkamaiThreatEvents.php] dataRows not found in response: '.$json_response);
                }
            }
        // if we haven't collected all the records yet then loop
        } while ($running_count < $total_records);

        Log::info('[GetAkamaiThreatEvents.php] DONE!');
    }
}
