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
        /*
         * Commands run on monthly schedule
         */

        /*
         * Commands run on weekly-ish schedule
         */
        $schedule->command('get:securitycentervulns')->weekly()->tuesdays()->at('18:00')->timezone('America/Chicago');  // runs on Tuesdays at 06:00pm

        /*
         * Commands run on daily schedule
         */
        //$schedule->command('get:ironportthreats')->dailyAt('02:00')->timezone('America/Chicago');           // runs daily at 02:00am
        //$schedule->command('get:incomingemail')->dailyAt('02:05')->timezone('America/Chicago');             // runs daily at 02:05am

        $schedule->command('get:sccmsystems')->dailyAt('05:00')->timezone('America/Chicago');               // runs daily at 05:00am

        $schedule->command('get:sitesubnets')->dailyAt('09:00')->timezone('America/Chicago');               // runs daily at 09:00am

        $schedule->command('get:cmdbservers')->dailyAt('20:30')->timezone('America/Chicago');               // runs daily at 08:30pm

        $schedule->command('get:severitysummary')->dailyAt('22:00')->timezone('America/Chicago');           // runs daily at 10:00pm
        $schedule->command('get:sumipvulns')->dailyAt('22:05')->timezone('America/Chicago');                // runs daily at 10:05pm
        $schedule->command('get:securitycenterassetvulns')->dailyAt('22:30')->timezone('America/Chicago');  // runs daily at 10:30pm

        /*
         * Commands run multiple times a day
         */
        $schedule->command('get:cylancedevices')->twiceDaily(8, 10)->timezone('America/Chicago');           // runs twice daily at 08:00am and 10:00am
        $schedule->command('get:cylancedevices')->twiceDaily(12, 14)->timezone('America/Chicago');          // runs twice daily at 12:00pm and 02:00pm
        $schedule->command('get:cylancedevices')->twiceDaily(16, 18)->timezone('America/Chicago');          // runs twice daily at 04:00pm and 06:00pm

        $schedule->command('get:cylancethreats')->twiceDaily(7, 9)->timezone('America/Chicago');            // runs twice daily at 07:00am and 09:00am
        $schedule->command('get:cylancethreats')->twiceDaily(11, 13)->timezone('America/Chicago');          // runs twice daily at 11:00am and 01:00pm
        $schedule->command('get:cylancethreats')->twiceDaily(15, 17)->timezone('America/Chicago');          // runs twice daily at 03:00pm and 05:00pm

        //$schedule->command('get:spamemail')->twiceDaily(4, 16)->timezone('America/Chicago');                // runs twice daily at 04:00am and 04:00pm

        /*
         * Commands run on an hourly basis
         */
        $schedule->command('get:securitytasks')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');             // runs hourly on week days between 06:00am and 06:00pm
        $schedule->command('get:idmtasks')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');                  // runs hourly on week days between 06:00am and 06:00pm

        $schedule->command('get:idmincidents')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');              // runs hourly on week days between 06:00am and 06:00pm
        $schedule->command('get:securityincidents')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');         // runs hourly on week days between 06:00am and 06:00pm

        $schedule->command('get:activethreatdetails')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');        // runs hourly on week days between 06:00am and 06:00pm
        $schedule->command('get:allowedthreatdetails')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');       // runs hourly on week days between 06:00am and 06:00pm
        $schedule->command('get:blockedthreatdetails')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');       // runs hourly on week days between 06:00am and 06:00pm
        $schedule->command('get:suspiciousthreatdetails')->weekdays()->hourly()->between('06:00', '18:00')->timezone('America/Chicago');    // runs hourly on week days between 06:00am and 06:00pm

        /*
         * Commands run every minute
         */
        $schedule->command('get:proofpointsiem')->everyMinute()->withoutOverlapping(2)->timezone('America/Chicago');
        $schedule->command('get:casalertshigh')->everyMinute()->withoutOverlapping(2)->timezone('America/Chicago');
        $schedule->command('get:casalertsmedium')->everyMinute()->withoutOverlapping(2)->timezone('America/Chicago');
        $schedule->command('get:casalertslow')->everyMinute()->withoutOverlapping(2)->timezone('America/Chicago');
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
