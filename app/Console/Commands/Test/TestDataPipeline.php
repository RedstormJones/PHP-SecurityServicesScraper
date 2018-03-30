<?php

namespace App\Console\Commands\Test;

use Illuminate\Console\Command;

class TestDataPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:datapipe {kafka_topic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test data pipeline by sending a test log to the provided Kafka topic';

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
        $kafka_topic = $this->argument('kafka_topic');

        $test_log = file_get_contents(storage_path('app/tests/test.log'));





        // instantiate Kafka producer config and set broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate Kafka producer
        $producer = new \Kafka\Producer();

        // ship data to Kafka
        $result = $producer->send([
            [
                'topic' => 'servicenow_cmdb_servers',
                'value' => \Metaclassing\Utility::encodeJson($server),
            ],
        ]);

        // check for and log errors
        if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
            Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
        } else {
            Log::info('[*] Data successfully sent to Kafka: '.$server['name']);
        }

    }

    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
