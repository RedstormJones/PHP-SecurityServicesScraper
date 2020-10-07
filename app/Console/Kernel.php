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
        // Commands run on daily schedule
        //$schedule->command('get:sitesubnets')->dailyAt('09:00')->timezone('America/Chicago');             // runs daily at 09:00am
        //$schedule->command('get:cmdbservers')->dailyAt('20:30')->timezone('America/Chicago');             // runs daily at 08:30pm
        $schedule->command('get:newdomains')->daily()->timezone('America/Chicago');                         // runs daily at midnight

        // Commands run multiple times a day
        //$schedule->command('get:sccmsystems')->twiceDaily(5, 13)->timezone('America/Chicago');              // runs twice daily at 05:00am and 01:00pm

        // Commands run on an hourly basis
        $schedule->command('get:nexposesites')->hourly()->timezone('America/Chicago');                      // runs hourly
        $schedule->command('get:graphsecurityalerts')->hourly()->timezone('America/Chicago');               // runs hourly
        $schedule->command('get:threatindicators')->hourly()->timezone('America/Chicago');                  // runs hourly
        $schedule->command('get:phishlabsincidents')->hourly()->timezone('America/Chicago');                // runs hourly
        

        // Commands run every five or ten minutes
        $schedule->command('check:winlogbeat')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                  // runs every ten minutes between the hours of 05:00am and 11:00pm
        $schedule->command('check:elastalert')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                  // runs every ten minutes between the hours of 05:00am and 11:00pm
        $schedule->command('check:syslog')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                      // runs every ten minutes between the hours of 05:00am and 11:00pm
        //$schedule->command('check:syslogmcas')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                  // runs every ten minutes between the hours of 05:00am and 11:00pm
        //$schedule->command('check:mfasyslog')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                   // runs every ten minutes between the hours of 05:00am and 11:00pm
        $schedule->command('check:netflow')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                     // runs every ten minutes between the hours of 05:00am and 11:00pm
        $schedule->command('check:packetbeat')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                  // runs every ten minutes between the hours of 05:00am and 11:00pm
        $schedule->command('check:phantom')->everyTenMinutes()->between('05:00', '23:00')->timezone('America/Chicago');                     // runs every ten minutes between the hours of 05:00am and 11:00pm

        $schedule->command('get:proofpointsiem')->everyTenMinutes()->timezone('America/Chicago');

        // Commands run every minute
        $schedule->command('get:casalertshigh')->everyMinute()->withoutOverlapping(1)->timezone('America/Chicago');
        $schedule->command('get:casalertsmedium')->everyMinute()->withoutOverlapping(1)->timezone('America/Chicago');
        $schedule->command('get:casalertslow')->everyMinute()->withoutOverlapping(1)->timezone('America/Chicago');

        //$schedule->command('get:defenderatpalerts')->everyMinute()->withoutOverlapping(1)->timezone('America/Chicago');
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
