<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSecurityTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:securitytasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Service Now security tasks';

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
         * [1] Get security tasks
         */

        Log::info(PHP_EOL.PHP_EOL.'************************************'.PHP_EOL.'* Starting security tasks crawler! *'.PHP_EOL.'************************************');

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/servicenow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url to incidents table and add necessary query params
        $assignment_group = urlencode('KTG SEC - Ops-Eng-IR');
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/task?sysparm_display_value=true&active=true&assignment_group='.$assignment_group;

        // setup HTTP headers with basic auth
        $headers = [
            'accept: application/json',
            'authorization: Basic '.getenv('SERVICENOW_AUTH'),
            'cache-control: no-cache',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // send request and capture response
        $json_response = $crawler->get($url);

        // dump response to file
        file_put_contents(storage_path('app/responses/security_tasks.dump'), $json_response);

        // JSON decode response and extract result data
        $response = json_decode($json_response, true);

        $tasks = $response['result'];
        Log::info('total security tasks count: '.count($tasks));

        $security_tasks = [];

        foreach ($tasks as $task) {
            $parent = $this->handleNull($task['parent']);
            $updated_on = $this->handleNull($task['sys_updated_on']);
            $updated_by = $this->handleNull($task['sys_updated_by']);
            $opened_by = $this->handleNull($task['opened_by']);
            $closed_at = $this->handleNull($task['closed_at']);
            $closed_by = $this->handleNull($task['closed_by']);
            $close_notes = $this->handleNull($task['close_notes']);
            $initial_assign_group = $this->handleNull($task['u_initial_assignment_group']);
            $assign_group = $this->handleNull($task['assignment_group']);
            $assigned_to = $this->handleNull($task['assigned_to']);
            $time_worked = $this->handleNull($task['time_worked']);
            $work_notes = $this->handleNull($task['work_notes']);
            $comments = $this->handleNull($task['comments']);
            $district = $this->handleNull($task['u_district_name']);
            $company = $this->handleNull($task['company']);
            $department = $this->handleNull($task['department']);
            $location = $this->handleNull($task['location']);
            $cause_code = $this->handleNull($task['u_cause_code']);
            $sys_domain = $this->handleNull($task['sys_domain']);
            $cmdb_ci = $this->handleNull($task['cmdb_ci']);
            $project_ref = $this->handleNull($task['u_project_ref']);
            $due_date = $this->handleNull($task['due_date']);
            $expected_start = $this->handleNull($task['expected_start']);
            $work_start = $this->handleNull($task['work_start']);
            $work_end = $this->handleNull($task['work_end']);
            $internal_name = $this->handleNull($task['u_internal_name']);

            $created_on_pieces = explode(' ', $task['sys_created_on']);
            $created_on_date = $created_on_pieces[0].'T'.$created_on_pieces[1];

            $opened_at_pieces = explode(' ', $task['opened_at']);
            $opened_at_date = $opened_at_pieces[0].'T'.$opened_at_pieces[1];

            if ($due_date['display_value']) {
                $due_date_pieces = explode(' ', $due_date['display_value']);
                $due_date['display_value'] = $due_date_pieces[0].'T'.$due_date_pieces[1];
            }

            if ($expected_start['display_value']) {
                $expected_start_pieces = explode(' ', $expected_start['display_value']);
                $expected_start['display_value'] = $expected_start_pieces[0].'T'.$expected_start_pieces[1];
            }

            if ($updated_on['display_value']) {
                $updated_on_pieces = explode(' ', $updated_on['display_value']);
                $updated_on['display_value'] = $updated_on_pieces[0].'T'.$updated_on_pieces[1];
            }

            if ($closed_at['display_value']) {
                $closed_at_pieces = explode(' ', $closed_at['display_value']);
                $closed_at['display_value'] = $closed_at_pieces[0].'T'.$closed_at_pieces[1];
            }

            if ($work_start['display_value']) {
                $work_start_pieces = explode(' ', $work_start['display_value']);
                $work_start['display_value'] = $work_start_pieces[0].'T'.$work_start_pieces[1];
            }

            if ($work_end['display_value']) {
                $work_end_pieces = explode(' ', $work_end['display_value']);
                $work_end['display_value'] = $work_end_pieces[0].'T'.$work_end_pieces[1];
            }

            $security_tasks[] = [
                'urgency'                       => $task['urgency'],
                'group_list'                    => $task['group_list'],
                'active'                        => $task['active'],
                'sys_updated_by'                => $updated_by['display_value'],
                'u_initiatives'                 => $task['u_initiatives'],
                'time_worked'                   => $time_worked['display_value'],
                'priority'                      => $task['priority'],
                'u_assignment_group_changed'    => $task['u_assignment_group_changed'],
                'additional_assignee_list'      => $task['additional_assignee_list'],
                'u_attached_knowledge_stream'   => $task['u_attached_knowledge_stream'],
                'approval_history'              => $task['approval_history'],
                'u_task_preferred_contact'      => $task['u_task_preferred_contact'],
                'u_project_ref'                 => $project_ref['display_value'],
                'expected_start'                => $expected_start['display_value'],
                'comments_and_work_notes'       => $task['comments_and_work_notes'],
                'close_notes'                   => $close_notes['display_value'],
                'correlation_display'           => $task['correlation_display'],
                'sys_domain'                    => $sys_domain['display_value'],
                'approval'                      => $task['approval'],
                'company'                       => $company['display_value'],
                'sys_created_on'                => $created_on_date,
                'follow_up'                     => $task['follow_up'],
                'escalation'                    => $task['escalation'],
                'location'                      => $location['display_value'],
                'u_impacted_services'           => $task['u_impacted_services'],
                'u_reassignment_count_non_kss'  => $task['u_reassignment_count_non_kss'],
                'sys_created_by'                => $task['sys_created_by'],
                'correlation_id'                => $task['correlation_id'],
                'calendar_duration'             => $task['calendar_duration'],
                'made_sla'                      => $task['made_sla'],
                'work_start'                    => $task['work_start'],
                'number'                        => $task['number'],
                'u_sub_state'                   => $task['u_sub_state'],
                'sys_id'                        => $task['sys_id'],
                'u_initial_assignment_group'    => $initial_assign_group['display_value'],
                'upon_reject'                   => $task['upon_reject'],
                'u_auto_close_date'             => $task['u_auto_close_date'],
                'opened_at'                     => $opened_at_date,
                'reassignment_count'            => $task['reassignment_count'],
                'assignment_group'              => $assign_group['display_value'],
                'impact'                        => $task['impact'],
                'approval_set'                  => $task['approval_set'],
                'business_duration'             => $task['business_duration'],
                'sla_due'                       => $task['sla_due'],
                'activity_due'                  => $task['activity_due'],
                'cmdb_ci'                       => $cmdb_ci['display_value'],
                'watch_list'                    => $task['watch_list'],
                'sys_mod_count'                 => $task['sys_mod_count'],
                'upon_approval'                 => $task['upon_approval'],
                'knowledge'                     => $task['knowledge'],
                'assigned_to'                   => $assigned_to['display_value'],
                'contact_type'                  => $task['contact_type'],
                'u_internal_name'               => $internal_name['display_value'],
                'closed_by'                     => $closed_by['display_value'],
                'user_input'                    => $task['user_input'],
                'department'                    => $department['display_value'],
                'u_followup_outlook_notify'     => $task['u_followup_outlook_notify'],
                'description'                   => $task['description'],
                'due_date'                      => $due_date['display_value'],
                'short_description'             => $task['short_description'],
                'skills'                        => $task['skills'],
                'sys_class_name'                => $task['sys_class_name'],
                'order'                         => $task['order'],
                'opened_by'                     => $opened_by['display_value'],
                'u_cause_code'                  => $cause_code['display_value'],
                'u_district_name'               => $district['display_value'],
                'sys_tags'                      => $task['sys_tags'],
                'sys_updated_on'                => $updated_on['display_value'],
                'business_service'              => $task['business_service'],
                'u_vendor_name_task'            => $task['u_vendor_name_task'],
                'closed_at'                     => $closed_at['display_value'],
                'work_notes_list'               => $task['work_notes_list'],
                'comments'                      => $comments['display_value'],
                'service_offering'              => $task['service_offering'],
                'work_end'                      => $task['work_end'],
                'work_notes'                    => $work_notes['display_value'],
                'parent'                        => $parent['display_value'],
                'state'                         => $task['state'],
            ];
        }

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/security_tasks_collection.json'), \Metaclassing\Utility::encodeJson($security_tasks));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($security_tasks as $task) {
            // add upsert datetime
            $task['upsert_date'] = Carbon::now()->toAtomString();

            $result = $producer->send([
                [
                    'topic' => 'servicenow_security_tasks',
                    'value' => \Metaclassing\Utility::encodeJson($task),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$task['number']);
            }
        }

        Log::info('* Completed ServiceNow security tasks! *');
    }

    /**
     * Function to handle null values.
     *
     * @return array
     */
    public function handleNull($data)
    {
        // if data is not null then check if 'display_value' is set
        if ($data) {
            // if 'display_value' is set then just return data
            if (isset($data['display_value'])) {
                return $data;
            } else {
                /*
                * otherwise we're dealing with a date string, so create a variable
                * and set the key 'display_value' to the date string
                */
                $some_data['display_value'] = $data;

                return $some_data;
            }
        } else {
            /*
            * otherwise if data is null then create and set the key
            * 'display_value' to the literal string 'null' and return it
            */
            $some_data['display_value'] = null;

            return $some_data;
        }
    }
}
