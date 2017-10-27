<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetProofPointSIEM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:proofpointsiem';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get data from ProofPoint SIEM API';

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
        /********************************
         * [1] Get ProofPoint SIEM data *
         ********************************/

        Log::info(PHP_EOL.PHP_EOL.'*************************************'.PHP_EOL.'* Starting ProofPoint SIEM command! *'.PHP_EOL.'*************************************');

        $cookiejar = storage_path('app/cookies/proofpointcookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=60';

        $json_response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/proofpoint.response'), $json_response);

        if (\Metaclassing\Utility::isJson($json_response)) {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            file_put_contents(storage_path('app/collections/proofpoint_siem.json'), \Metaclassing\Utility::encodeJson($response));

            $config = \Kafka\ProducerConfig::getInstance();
            $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
            $producer = new \Kafka\Producer();

            $response['UpsertDate'] = Carbon::now()->toAtomString();

            $result = $producer->send([
                [
                    'topic' => 'proofpoint_siem',
                    'value' => \Metaclassing\Utility::encodeJson($response),
                ],
            ]);

            Log::info($result);
            /*
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] ProofPoint SIEM data successfully sent to Kafka');
            }
            */
        } else {
            Log::error('[!] ProofPoint response not valid JSON: '.json_last_error());
            die('[!] ERROR: ProofPoint response not valid JSON: '.json_last_error().PHP_EOL);
        }

        Log::info('* ProofPoint SIEM command completed! *'.PHP_EOL);
    }
}
