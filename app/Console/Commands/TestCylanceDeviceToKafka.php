<?php

namespace App\Console\Commands;

use App\Jobs\SendCylanceDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCylanceDeviceToKafka extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cylancedevicetokafka';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test command for sending Cylance devices to a Kafka topic';

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
        $contents = file_get_contents(storage_path('app/collections/cylance_devices.json'));
        $cylance_devices = \Metaclassing\Utility::decodeJson($contents);

        // cycle through Cylance devices
        foreach ($cylance_devices as $cylance_device) {
            $job = (new SendCylanceDevice($cylance_device))->onConnection('kafka')->onQueue('cylance_devices');
            Log::info($job->cylance_device);
            dispatch($job);
        }
    }
}
