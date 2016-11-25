<?php

namespace App\Console\Commands;

use App\Lancope\InsideHostTrafficSnapshot;
use Illuminate\Console\Command;

class ProcessInsideHostTrafficSnapshots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:insidehosttrafficsnapshots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new inside host app traffic snapshots and update database';

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
        $contents = file_get_contents(storage_path('app/collections/insidehost_apptraffic.json'));
        $apptraffic = \Metaclassing\Utility::decodeJson($contents);

        foreach ($apptraffic as $app) {
            echo 'creating new record for '.$app['applicationName'].' during '.$app['timePeriod'].PHP_EOL;
            $snapshot = new InsideHostTrafficSnapshot();

            $snapshot->application_id = $app['applicationId'];
            $snapshot->application_name = $app['applicationName'];
            $snapshot->time_period = $app['timePeriod'];
            $snapshot->traffic_outbound_Bps = $app['trafficOutboundBps'];
            $snapshot->traffic_inbound_Bps = $app['trafficInboundBps'];
            $snapshot->traffic_within_Bps = $app['trafficWithinBps'];
            $snapshot->data = \Metaclassing\Utility::encodeJson($app);

            $snapshot->save();
        }

        $this->processDeletes();
    }

    /**
     * Function to process softdeletes on application traffic snapshots.
     *
     * @return void
     */
    public function processDeletes()
    {
        $today = new \DateTime('now');
        $deleteday = $today->modify('-7 days');
        $delete_date = $deleteday->format('Y-m-d H:i:s');

        $apptraffic_snapshots = InsideHostTrafficSnapshot::where('updated_at', '<', $delete_date)->get();

        foreach ($apptraffic_snapshots as $snapshot) {
            echo 'deleting record for '.$snapshot->application_name.' during time period '.$snapshot->time_period.PHP_EOL;
            $snapshot->delete();
        }
    }

   // end of function processDeletes()
}   // end of ProcessInsideHostTrafficSnapshots command class