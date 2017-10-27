<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetMFATickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:mfatickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get MFA related work tickets from ServiceNow';

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
        /*
         * [1] Get ServiceNow incidents and tasks
         */

        Log::info(PHP_EOL.PHP_EOL.'*********************************'.PHP_EOL.'* Starting MFA tickets crawler! *'.PHP_EOL.'*********************************');

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/servicenow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup HTTP headers with basic auth
        $headers = [
            'accept: application/json',
            'authorization: Basic '.getenv('SERVICENOW_AUTH'),
            'cache-control: no-cache',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $this->getIncidents($crawler);
        $this->getTasks($crawler);

        Log::info('* Completed ServiceNow MFA incidents and tasks! *');
    }

    /**
     * Get ServiceNow incidents.
     *
     * @return mixed
     */
    public function getIncidents($crawler)
    {
        $mfa_regex = '/.*(MFA|Multifactor|multifactor|Multi-factor|multi-factor|2 factor|2-factor|2factor).*/';

        $incident_set = [];
        $sec_url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/incident?sysparm_display_value=true&assignment_group=IM%20SEC%20-%20Security';
        $kss_svc_url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/incident?sysparm_display_value=true&assignment_group=IM%20KSS%20-%20Service%20Desk';

        // query for incidents and dump to file
        Log::info('[-] querying for ServiceNow incidents...');

        $sec_response = $crawler->get($sec_url);
        file_put_contents(storage_path('app/responses/security_mfa_incidents.json'), $sec_response);
        $response = \Metaclassing\Utility::decodeJson($sec_response);
        $incident_set[] = $response['result'];

        $kss_response = $crawler->get($kss_svc_url);
        file_put_contents(storage_path('app/responses/kss_mfa_incidents.json'), $kss_response);
        $response = \Metaclassing\Utility::decodeJson($kss_response);
        $incident_set[] = $response['result'];

        $incidents = [];
        foreach ($incident_set as $set) {
            foreach ($set as $incident) {
                $incidents[] = $incident;
            }
        }
        Log::info('[-] total MFA incidents count: '.count($incidents));

        foreach ($incidents as $incident) {
            //Log::info('[-] checking incident: '.$incident['number'].' - '.$incident['short_description']);
            if (preg_match($mfa_regex, $incident['short_description']) === 1 || preg_match($mfa_regex, $incident['description']) === 1) {
                Log::info('[+] MFA incident found: '.$incident['short_description']);
            }
        }
    }

    /**
     * Get ServiceNow tasks.
     *
     * @return mixed
     */
    public function getTasks($crawler)
    {
        $mfa_regex = '/.*(MFA|Multifactor|multifactor|Multi-factor|multi-factor|2 factor|2-factor|2factor).*/';

        $task_set = [];
        $sec_url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/task?sysparm_display_value=true&assignment_group=IM%20SEC%20-%20Security';
        $kss_svc_url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/task?sysparm_display_value=true&assignment_group=IM%20KSS%20-%20Service%20Desk';

        // query for tasks and dump to file
        Log::info('[-] querying for ServiceNow tasks...');

        $sec_response = $crawler->get($sec_url);
        file_put_contents(storage_path('app/responses/security_mfa_tasks.json'), $sec_response);
        $response = \Metaclassing\Utility::decodeJson($sec_response);
        $task_set[] = $response['result'];

        $kss_response = $crawler->get($kss_svc_url);
        file_put_contents(storage_path('app/responses/kss_mfa_tasks.json'), $kss_response);
        $response = \Metaclassing\Utility::decodeJson($kss_response);
        $task_set[] = $response['result'];

        $tasks = [];
        foreach ($task_set as $set) {
            foreach ($set as $task) {
                $tasks[] = $task;
            }
        }
        Log::info('[-] total MFA tasks count: '.count($tasks));

        foreach ($tasks as $task) {
            //Log::info('[-] checking task: '.$task['number'].' - '.$task['short_description']);
            if (preg_match($mfa_regex, $task['short_description']) === 1 || preg_match($mfa_regex, $task['description']) === 1) {
                Log::info('[+] MFA task found: '.$task['short_description']);
            }
        }
    }
}
