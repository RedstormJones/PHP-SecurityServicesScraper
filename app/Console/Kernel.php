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
        Commands\Get\GetCylanceDevices::class,
        Commands\Get\GetCylanceThreats::class,
        Commands\Get\GetCMDBServers::class,
        Commands\Get\GetIncomingEmail::class,
        Commands\Get\GetIronPortThreats::class,
        Commands\Get\GetSecurityIncidents::class,
        Commands\Get\GetIDMIncidents::class,
        Commands\Get\GetSAPRoleAuthIncidents::class,
        Commands\Get\GetSecurityTasks::class,
        Commands\Get\GetIDMTasks::class,
        Commands\Get\GetSAPRoleAuthTasks::class,
        Commands\Get\GetInsideHostTrafficSnapshots::class,
        Commands\Get\GetOutsideHostTrafficSnapshots::class,
        Commands\Get\GetPhishMeScenarios::class,
        Commands\Get\GetSiteSubnets::class,
        Commands\Get\GetSpamEmail::class,
        Commands\Get\GetSecurityCenterAssetVulns::class,
        Commands\Get\GetSecurityCenterVulns::class,
        Commands\Get\GetSCCMSystems::class,
        Commands\Process\ProcessCylanceDevices::class,
        Commands\Process\ProcessCylanceThreats::class,
        Commands\Process\ProcessIncomingEmail::class,
        Commands\Process\ProcessSpamEmail::class,
        Commands\Process\ProcessIronPortThreats::class,
        Commands\Process\ProcessSecurityCenterCriticals::class,
        Commands\Process\ProcessSecurityCenterHighs::class,
        Commands\Process\ProcessSecurityCenterMediums::class,
        Commands\Process\ProcessSecurityCenterAssetVulns::class,
        Commands\Process\ProcessPhishMeScenarios::class,
        Commands\Process\ProcessSiteSubnets::class,
        Commands\Process\ProcessInsideHostTrafficSnapshots::class,
        Commands\Process\ProcessOutsideHostTrafficSnapshots::class,
        Commands\Process\ProcessSecurityIncidents::class,
        Commands\Process\ProcessCMDBServers::class,
        Commands\Process\ProcessIdmIncidents::class,
        Commands\Process\ProcessSapRoleAuthIncidents::class,
        Commands\Crawl\CrawlCylanceDevices::class,
        Commands\Crawl\CrawlCylanceThreats::class,
        Commands\Crawl\CrawlIncomingEmails::class,
        Commands\Crawl\CrawlSpamEmails::class,
        Commands\Crawl\CrawlIronPortThreats::class,
        Commands\Crawl\CrawlSecurityCenterVulns::class,
        Commands\Crawl\CrawlSecurityCenterAssetVulns::class,
        Commands\Crawl\CrawlPhishMeScenarios::class,
        Commands\Crawl\CrawlSiteSubnets::class,
        Commands\Crawl\CrawlInsideHostTrafficSnapshots::class,
        Commands\Crawl\CrawlOutsideHostTrafficSnapshots::class,
        Commands\Crawl\CrawlSecurityIncidents::class,
        Commands\Crawl\CrawlCMDBServers::class,
        Commands\Crawl\CrawlIdmIncidents::class,
        Commands\Crawl\CrawlSapRoleAuthIncidents::class,
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
        $schedule->command('get:phishmescenarios')->monthlyOn(1, '03:00')->timezone('America/Chicago');     // runs every month on the 1st at 03:00am

        /*
        * Commands run on weekly-ish schedule
        */
        $schedule->command('get:securitycentervulns')->weekly()->tuesdays()->at('18:00')->timezone('America/Chicago');  // runs on Tuesdays at 06:00pm

        /*
        * Commands run on daily schedule
        */
        $schedule->command('get:ironportthreats')->dailyAt('02:00')->timezone('America/Chicago');           // runs daily at 02:00am
        $schedule->command('get:incomingemail')->dailyAt('02:05')->timezone('America/Chicago');             // runs daily at 02:05am

        $schedule->command('get:sitesubnets')->dailyAt('09:00')->timezone('America/Chicago');               // runs daily at 09:00am

        $schedule->command('get:cmdbservers')->dailyAt('20:30')->timezone('America/Chicago');               // runs daily at 08:30pm

        $schedule->command('get:securitycenterassetvulns')->dailyAt('22:30')->timezone('America/Chicago');  // runs daily at 10:30pm

        /*
        * Commands run multiple times a day
        */
        $schedule->command('get:cylancedevices')->twiceDaily(0, 12)->timezone('America/Chicago');               // runs twice daily at 00:00am and 12:00pm
        $schedule->command('get:cylancethreats')->twiceDaily(1, 13)->timezone('America/Chicago');               // runs twice daily at 01:00am and 01:00pm

        $schedule->command('get:insidehosttrafficsnapshots')->twiceDaily(8, 14)->timezone('America/Chicago');   // runs twice daily at 08:00am and 02:00pm
        $schedule->command('get:outsidehosttrafficsnapshots')->twiceDaily(9, 15)->timezone('America/Chicago');  // runs twice daily at 09:00am and 03:00pm

        $schedule->command('get:spamemail')->twiceDaily(9, 14)->timezone('America/Chicago');                    // runs twice daily at 09:00am and 02:00pm

        /*
        * Commands run on an hourly basis
        */
        //$schedule->command('get:securitytasks')->hourly()->weekdays()->timezone('America/Chicago');             // runs hourly on week days
        $schedule->command('get:idmtasks')->hourly()->weekdays()->timezone('America/Chicago');                  // runs hourly on week days
        $schedule->command('get:saproleauthtasks')->hourly()->weekdays()->timezone('America/Chicago');          // runs hourly on week days

        $schedule->command('get:saproleauthincidents')->hourly()->weekdays()->timezone('America/Chicago');      // runs hourly on week days
        $schedule->command('get:idmincidents')->hourly()->weekdays()->timezone('America/Chicago');              // runs hourly on week days
        $schedule->command('get:securityincidents')->hourly()->weekdays()->timezone('America/Chicago');         // runs hourly on week days
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
