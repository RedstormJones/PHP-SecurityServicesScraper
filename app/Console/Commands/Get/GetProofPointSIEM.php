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

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth header and assign to crawler
        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        // define target url
        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=3600';

        // send GET request to url and dump response to file
        $json_response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/proofpoint.response'), $json_response);

        // try to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $error = 'No errors detected';
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // check if decoding the JSON response was successful
        if (!\Metaclassing\Utility::testJsonError()) {
            file_put_contents(storage_path('app/collections/proofpoint_siem.json'), \Metaclassing\Utility::encodeJson($response));

            $messages_delivered = $response['messagesDelivered'];
            $messages_blocked = $response['messagesBlocked'];
            $clicks_permitted = $response['clicksPermitted'];
            $clicks_blocked = $response['clicksBlocked'];

            if (count($messages_delivered) === 0 AND
                count($messages_blocked) === 0 AND
                count($clicks_permitted) === 0 AND
                count($clicks_blocked) === 0)
            {
                Log::info('[*] no new data retrieved from ProofPoint - terminating execution');
                die('[*] no new data retrieved from ProofPoint - terminating execution...'.PHP_EOL);
            }

            // setup a Kafka producer
            $config = \Kafka\ProducerConfig::getInstance();
            $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
            $producer = new \Kafka\Producer();

            // add the current time to the data
            $response['UpsertDate'] = Carbon::now()->toAtomString();

            // send data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'proofpoint_siem',
                    'value' => \Metaclassing\Utility::encodeJson($response),
                ],
            ]);

            // check for errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] ProofPoint SIEM data successfully sent to Kafka');
            }
        } else {
            // otherwise pop smoke and bail
            Log::error('[!] ProofPoint response not valid JSON: '.$error);
            die('[!] ERROR: ProofPoint response not valid JSON: '.$error.PHP_EOL);
        }

        Log::info('[*] ProofPoint SIEM command completed! [*]'.PHP_EOL);
    }
}
