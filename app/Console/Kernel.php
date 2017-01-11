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
        Commands\GetCylanceDevices::class,
        Commands\GetCylanceThreats::class,
        Commands\GetCMDBServers::class,
        Commands\GetIncomingEmail::class,
        Commands\GetIronPortThreats::class,
        Commands\GetSecurityIncidents::class,
        Commands\GetIDMIncidents::class,
        Commands\GetSAPRoleAuthIncidents::class,
        Commands\GetSecurityTasks::class,
        Commands\GetIDMTasks::class,
        Commands\GetSAPRoleAuthTasks::class,
        Commands\GetInsideHostTrafficSnapshots::class,

        Commands\ProcessCylanceDevices::class,
        Commands\ProcessCylanceThreats::class,
        Commands\ProcessIncomingEmail::class,
        Commands\ProcessSpamEmail::class,
        Commands\ProcessIronPortThreats::class,
        Commands\ProcessSecurityCenterCriticals::class,
        Commands\ProcessSecurityCenterHighs::class,
        Commands\ProcessSecurityCenterMediums::class,
        Commands\ProcessSecurityCenterAssetVulns::class,
        Commands\ProcessPhishMeScenarios::class,
        Commands\ProcessSiteSubnets::class,
        Commands\ProcessInsideHostTrafficSnapshots::class,
        Commands\ProcessOutsideHostTrafficSnapshots::class,
        Commands\ProcessSecurityIncidents::class,
        Commands\ProcessCMDBServers::class,
        Commands\ProcessIdmIncidents::class,
        Commands\ProcessSapRoleAuthIncidents::class,
        Commands\CrawlCylanceDevices::class,
        Commands\CrawlCylanceThreats::class,
        Commands\CrawlIncomingEmails::class,
        Commands\CrawlSpamEmails::class,
        Commands\CrawlIronPortThreats::class,
        Commands\CrawlSecurityCenterVulns::class,
        Commands\CrawlSecurityCenterAssetVulns::class,
        Commands\CrawlPhishMeScenarios::class,
        Commands\CrawlSiteSubnets::class,
        Commands\CrawlInsideHostTrafficSnapshots::class,
        Commands\CrawlOutsideHostTrafficSnapshots::class,
        Commands\CrawlSecurityIncidents::class,
        Commands\CrawlCMDBServers::class,
        Commands\CrawlIdmIncidents::class,
        Commands\CrawlSapRoleAuthIncidents::class,
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
        $schedule->command('get:ironportthreats')->monthlyOn(1, '21:00')->timezone('America/Chicago');      // runs every month on the 1st at 09:00pm

        $schedule->command('crawl:phishmescenarios')->monthlyOn(1, '22:30')->timezone('America/Chicago');    // runs every month on the 1st at 10:30pm
        $schedule->command('process:phishmescenarios')->monthlyOn(1, '23:00')->timezone('America/Chicago');  // runs every month on the 1st at 11:00pm

        /*
        * Commands run on weekly schedule
        */
        $schedule->command('crawl:securitycenterassetvulns')->weekly()->thursdays()->at('17:30')->timezone('America/Chicago');   // runs weekly on Thursdays at 5:30pm
        $schedule->command('process:securitycenterassetvulns')->weekly()->thursdays()->at('17:45')->timezone('America/Chicago'); // runs weekly on Thursdays at 5:45pm
        $schedule->command('crawl:securitycentervulns')->weekly()->thursdays()->at('18:00')->timezone('America/Chicago');        // runs weekly on Thursdays at 6:00pm
        $schedule->command('process:securitycentercriticals')->weekly()->thursdays()->at('18:15')->timezone('America/Chicago');  // runs weekly on Thursdays at 6:15pm
        $schedule->command('process:securitycenterhighs')->weekly()->thursdays()->at('19:30')->timezone('America/Chicago');      // runs weekly on Thursdays at 7:30pm
        $schedule->command('process:securitycentermediums')->weekly()->thursdays()->at('21:00')->timezone('America/Chicago');    // runs weekly on Thursdays at 9:00pm

        $schedule->command('crawl:sitesubnets')->weekly()->fridays()->at('22:00')->timezone('America/Chicago');      // runs weekly on Fridays at 10:00pm
        $schedule->command('process:sitesubnets')->weekly()->fridays()->at('22:15')->timezone('America/Chicago');    // runs weekly on Fridays at 10:15pm

        /*
        * Commands run on daily schedule
        */
        $schedule->command('get:securitytasks')->dailyAt('19:00')->timezone('America/Chicago');         // runs daily at 07:00pm
        $schedule->command('get:idmtasks')->dailyAt('19:30')->timezone('America/Chicago');              // runs daily at 07:30pm

        $schedule->command('get:cmdbservers')->dailyAt('20:30')->timezone('America/Chicago');           // runs daily at 08:30pm
        $schedule->command('get:saproleauthincidents')->dailyAt('21:00')->timezone('America/Chicago');  // runs daily at 09:00pm
        $schedule->command('get:idmincidents')->dailyAt('21:30')->timezone('America/Chicago');          // runs daily at 09:30pm
        $schedule->command('get:securityincidents')->dailyAt('22:00')->timezone('America/Chicago');     // runs daily at 10:00pm

        $schedule->command('crawl:spamemails')->dailyAt('22:45')->timezone('America/Chicago');          // runs daily at 10:30pm
        $schedule->command('process:spamemail')->dailyAt('23:00')->timezone('America/Chicago');         // runs daily at 11:00pm

        $schedule->command('get:cylancedevices')->daily()->timezone('America/Chicago');                 // runs daily at 00:00
        $schedule->command('get:cylancethreats')->dailyAt('01:00')->timezone('America/Chicago');        // runs daily at 01:00
        $schedule->command('get:incomingemail')->dailyAt('02:00')->timezone('America/Chicago');         // runs daily at 02:00am

        /*
        * Commands run multiple times a day
        */
        $schedule->command('get:insidehosttrafficsnapshots')->twiceDaily(6, 12)->timezone('America/Chicago');

        $schedule->command('crawl:outsidehosttrafficsnapshots')->twiceDaily(8, 14)->timezone('America/Chicago');   // runs twice every day at 8:00am and 2:00pm
        $schedule->command('process:outsidehosttrafficsnapshots')->twiceDaily(9, 15)->timezone('America/Chicago'); // runs twice every day at 9:00am and 3:00pm
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
