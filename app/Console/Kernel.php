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
        Commands\CrawlCylanceDevices::class,
        Commands\CrawlCylanceThreats::class,
        Commands\CrawlIncomingEmails::class,
        Commands\CrawlSpamEmails::class,
        Commands\CrawlIronPortThreats::class,
        Commands\CrawlSecurityCenterVulns::class,
        Commands\CrawlSecurityCenterAssetVulns::class,
        Commands\CrawlPhishMeScenarios::class,
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
        $schedule->command('crawl:ironportthreats')->monthlyOn(1, '21:00');     // runs every month on the 1st at 09:00pm
        $schedule->command('process:ironportthreats')->monthlyOn(1, '21:30');    // runs every month on the 1st at 09:30pm
        $schedule->command('crawl:phishmescenarios')->monthlyOn(1, '22:30');    // runs every month on the 1st at 10:30pm
        $schedule->command('process:phishmescenarios')->monthlyOn(1, '23:00');    // runs every month on the 1st at 11:00pm

        /*
        * Commands run on weekly schedule
        */
        $schedule->command('crawl:securitycenterassetvulns')->weekly()->thursdays()->at('17:30');
        $schedule->command('process:securitycenterassetvulns')->weekly()->thursdays()->at('17:45');
        $schedule->command('crawl:securitycentervulns')->weekly()->thursdays()->at('18:00');
        $schedule->command('process:securitycentercriticals')->weekly()->thursdays()->at('18:15');
        $schedule->command('process:securitycenterhighs')->weekly()->thursdays()->at('19:30');
        $schedule->command('process:securitycentermediums')->weekly()->thursdays()->at('21:00');

        /*
        * Commands run on daily schedule
        */
        $schedule->command('crawl:spamemails')->dailyAt('10:30');
        $schedule->command('process:spamemail')->dailyAt('11:00');
        $schedule->command('crawl:cylancedevices')->daily();                    // runs at midnight (00:00)
        $schedule->command('process:cylancedevices')->dailyAt('01:00');
        $schedule->command('crawl:cylancethreats')->dailyAt('03:30');
        $schedule->command('process:cylancethreats')->dailyAt('04:00');
        $schedule->command('crawl:incomingemail')->dailyAt('05:00');
        $schedule->command('process:incomingemail')->dailyAt('05:15');
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
