<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        /*
         * Commands\CommandName::class,
         */
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Commands run on an hourly basis
        $schedule->command('get:defenderatpalerts')->hourly()->timezone('America/Chicago');

        // Commands run every thirty minutes
        $schedule->command('get:saviyntlogs')->everyThirtyMinutes()->timezone('America/Chicago');

        // Commands run every ten minutes
        $schedule->command('get:trapincidents')->everyTenMinutes()->timezone('America/Chicago');
        $schedule->command('get:lastpassevents')->everyTenMinutes()->timezone('America/Chicago');
        //$schedule->command('get:ppclickspermitted')->everyTenMinutes()->timezone('America/Chicago');
        //$schedule->command('get:ppclicksblocked')->everyTenMinutes()->timezone('America/Chicago');
        //$schedule->command('get:ppmessagesdelivered')->everyTenMinutes()->timezone('America/Chicago');
        //$schedule->command('get:ppmessagesblocked')->everyTenMinutes()->timezone('America/Chicago');

        // Commands run every 5 minutes
        $schedule->command('get:endpointincidents')->everyFiveMinutes()->timezone('America/Chicago');
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');

        $this->load(__DIR__.'/Commands');
    }
}
