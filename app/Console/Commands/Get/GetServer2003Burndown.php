<?php

namespace App\Console\Commands\Get;

use App\SCCM\SCCMSystem;
use App\SCCM\Server2003Burndown;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetServer2003Burndown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:server2003burndown';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates burndown status for 2003 servers and update the database';

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
        /**********************************
         * Get 2003 servers burndown data *
         **********************************/

        Log::info(PHP_EOL.PHP_EOL.'**********************************************'.PHP_EOL.'* Starting 2003 servers burndown processing! *'.PHP_EOL.'**********************************************');

        $yesterday = Carbon::now()->subDay()->toDateTimeString();

        // get current count of 2003 servers
        $server_count = SCCMSystem::where('os_roundup', 'like', '%2003%')->count();
        Log::info('server count: '.$server_count);

        // get yesterday's count of 2003 servers
        $previous_count = Server2003Burndown::where('created_at', $yesterday)->value('server_count');
        Log::info('previous count: '.$previous_count);

        // if previous count is 0 or null then there was no previous day's record to use
        if ($previous_count == 0 || is_null($previous_count))
        {
            // so, set the previous count to the server count so that the trending value is 0
            $previous_count = $server_count;
        }

        // use today's count and yesterday's count to calculate a 24 hour trending value
        $trend_value = $server_count - $previous_count;
        Log::info('trend value: '.$trend_value);

        $data = [
            'server_count'  => $server_count,
            'trend_value'   => $trend_value,
        ];

        // create the new model
        Log::info('creating new 2003 server burndown model');

        $new_burndown = new Server2003Burndown();

        $new_burndown->server_count = $server_count;
        $new_burndown->trend_value = $trend_value;
        $new_burndown->data = \Metaclassing\Utility::encodeJson($data);

        $new_burndown->save();

        Log::info('* Completed 2003 servers burndown processing! *'.PHP_EOL);
    }
}
