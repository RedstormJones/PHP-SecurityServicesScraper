<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetAkamaiAUPEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:aupevents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Akamai AUP events';

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
        Log::info('[GetAkamaiAUPEvents.php] Starting Akamai AUP events API Poll!');

        // setup date string for output filename
        $output_date = Carbon::now()->toDateString();

        // setup timeframe (sec) for request argument
        $start_timeframe_secs = Carbon::now()->subMinutes(10)->timestamp;
        $end_timeframe_secs = Carbon::now()->timestamp;
        //Log::info('[GetAkamaiAUPEvents.php] startTimeSec: '.$start_timeframe_secs);
        //Log::info('[GetAkamaiAUPEvents.php] endTimeSec: '.$end_timeframe_secs);

        // config id
        $akamai_config_id = getenv('AKAMAI_CONFIG_ID');

        // setup variables for total and running counts and for page number
        $total_records = 0;
        $page_number = 1;
        $page_size = 1;

        // send initial request with pageNumber and pageSize set to 1
        // setup query and JSON encode it
        $query = [
                    'startTimeSec'  => $start_timeframe_secs,        
                    'endTimeSec'    => $end_timeframe_secs,
                    'orderBy'       => 'DESC',
                    'pageNumber'    => $page_number,
                    'pageSize'      => $page_size,
                    'filters'       => new \stdClass(),
                ];
        $query = json_encode($query);
        Log::info('[GetAkamaiAUPEvents.php] JSON encoded first query: '.$query);

        // setup auth stuff and add query
        $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile('default', '.edgerc');        
        $auth->setHttpMethod('POST');
        $auth->setPath('/etp-report/v3/configs/'.$akamai_config_id.'/aup-events/details');
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
        //Log::info('[GetAkamaiAUPEvents.php] querying for page number '.$page_number);

        // send request and capture response, otherwise dump response to file
        $response = file_get_contents('https://'.$auth->getHost().$auth->getPath(), null, $context);
        file_put_contents(storage_path('app/responses/akamai-aup-events-first.response'), $response);

        if ($response) {
            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($response);

            // check that pageInfo exists
            if (array_key_exists('pageInfo', $response)) {
                // get total records count from response
                $total_records = $response['pageInfo']['totalRecords'];
                Log::info('[GetAkamaiAUPEvents.php] total records from first query: '.$total_records);
            } else {
                // JSON encode, log response error and pop smoke
                $json_response = \Metaclassing\Utility::encodeJson($response);
                Log::error('[GetAkamaiAUPEvents.php] pageInfo not found in response: '.$json_response);
                die('[GetAkamaiAUPEvents.php] pageInfo not found in response: '.$json_response);
            }
        }

        // set page size to something bigger than 1 and calculate number of pages needed for total query
        $page_size = 500;
        $page_number = floor($total_records / $page_size);

        // if total records mod page size results in a value other than 0 then increment page number
        if ($total_records % $page_size != 0) {
            $page_number += 1;
        }

        // setup query to get all the records
        $query = [
            'startTimeSec'  => $start_timeframe_secs,        
            'endTimeSec'    => $end_timeframe_secs,
            'orderBy'       => 'DESC',
            'pageNumber'    => $page_number,
            'pageSize'      => $page_size,
            'filters'       => new \stdClass(),
        ];
        $query = json_encode($query);
        Log::info('[GetAkamaiAUPEvents.php] JSON encoded total query: '.$query);

        // setup auth stuff and add query
        $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile('default', '.edgerc');        
        $auth->setHttpMethod('POST');
        $auth->setPath('/etp-report/v3/configs/'.$akamai_config_id.'/aup-events/details');
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
        //Log::info('[GetAkamaiAUPEvents.php] querying for page number '.$page_number);

        // send request, capture response and dump response to file
        $response = file_get_contents('https://'.$auth->getHost().$auth->getPath(), null, $context);
        file_put_contents(storage_path('app/responses/akamai-aup-events-total.response'), $response);

        // check if response is empty
        if ($response) {
            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($response);

            // check if dataRows exists
            if (array_key_exists('dataRows', $response)) {
                // get AUP events from response dataRows
                $aup_events = $response['dataRows'];

                // get total records count from response
                $total_records = $response['pageInfo']['totalRecords'];
                Log::info('[GetAkamaiAUPEvents.php] total records from total query: '.$total_records);

                // cycle through AUP events
                foreach ($aup_events as $data) {
                    // JSON encode with newline and append to output file
                    $json_data = \Metaclassing\Utility::encodeJson($data)."\n";
                    file_put_contents(storage_path('app/output/akamai_aup_events/'.$output_date.'-akamai-aup-events.log'), $json_data, FILE_APPEND);
                }
            } else {
                // JSON encode, log response error and pop smoke
                $json_response = \Metaclassing\Utility::encodeJson($response);
                Log::error('[GetAkamaiAUPEvents.php] dataRows not found in response: '.$json_response);
                die('[GetAkamaiAUPEvents.php] dataRows not found in response: '.$json_response);
            }
        }

        Log::info('[GetAkamaiAUPEvents.php] DONE!');
    }
}
