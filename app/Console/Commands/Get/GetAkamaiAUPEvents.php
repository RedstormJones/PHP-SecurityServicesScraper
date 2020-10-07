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

        $date = Carbon::now();
        $timeframe = $date->subMinutes(10);
        $timeframe_secs = $timeframe->timestamp;

        $akamai_config_id = getenv('AKAMAI_CONFIG_ID');

        $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile('default', '.edgerc');
        $auth->setHttpMethod('GET');
        $auth->setPath('/etp-config/v1/configs/'.$akamai_config_id.'/aup-events/details?startTimeSec='.$timeframe_secs);
 
        $context = [
            'http'  => [
                'method'    => 'GET',
                'header'    => [
                    'Authorization: '.$auth->createAuthHeader(),
                    'Content-Type: application/json',
                ]
            ]
        ];
        $context = stream_context_create($context);

        $json_response = file_get_contents('https://'.$auth->getHost().$auth->getPath(), null, $context);
        file_put_contents(storage_path('app/responses/akamai-aup-events.response'), $json_response);

        if ($json_response) {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            var_dump($response);
        }


        Log::info('[GetAkamaiAUPEvents.php] DONE!');
    }
}
