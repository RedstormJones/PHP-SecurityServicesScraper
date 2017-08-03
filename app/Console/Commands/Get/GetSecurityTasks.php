<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\ServiceNow\ServiceNowSecurityTask;
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
    protected $description = 'Get new security tasks';

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
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/task?sysparm_display_value=true&assignment_group=IM%20SEC%20-%20Security&active=true';

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
                'u_customer_action'             => $task['u_customer_action'],
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
                'rejection_goto'                => $task['rejection_goto'],
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

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($security_tasks as $task) {
            $url = 'http://10.243.32.36:9200/security_tasks/security_tasks/'.$task['sys_id'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $task,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('Security task was successfully inserted into ES: '.$task['sys_id']);
            } else {
                Log::error('Something went wrong inserting Security task: '.$task['sys_id']);
                die('Something went wrong inserting Security task: '.$task['sys_id'].PHP_EOL);
            }
        }

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/security_tasks_collection.json'), \Metaclassing\Utility::encodeJson($security_tasks));

        /*
         * [2] Process security tasks into database
         */

        /*
        Log::info(PHP_EOL.'***************************************'.PHP_EOL.'* Starting security tasks processing! *'.PHP_EOL.'***************************************');

        foreach ($tasks as $task) {
            $exists = ServiceNowSecurityTask::where('sys_id', $task['sys_id'])->value('id');

            if ($exists) {
                $updated_on = $this->handleNull($task['sys_updated_on']);
                $updated_by = $this->handleNull($task['sys_updated_by']);
                $closed_at = $this->handleNull($task['closed_at']);
                $closed_by = $this->handleNull($task['closed_by']);
                $close_notes = $this->handleNull($task['close_notes']);
                $assign_group = $this->handleNull($task['assignment_group']);
                $assigned_to = $this->handleNull($task['assigned_to']);
                $time_worked = $this->handleNull($task['time_worked']);
                $work_notes = $this->handleNull($task['work_notes']);
                $comments = $this->handleNull($task['comments']);
                $cause_code = $this->handleNull($task['u_cause_code']);

                $taskmodel = ServiceNowSecurityTask::find($exists);
                $taskmodel->update([
                    'active'                => $task['active'],
                    'updated_on'            => $updated_on['display_value'],
                    'updated_by'            => $updated_by['display_value'],
                    'closed_at'             => $closed_at['display_value'],
                    'closed_by'             => $closed_by['display_value'],
                    'close_notes'           => $close_notes['display_value'],
                    'assignment_group'      => $assign_group['display_value'],
                    'assigned_to'           => $assigned_to['display_value'],
                    'state'                 => $task['state'],
                    'urgency'               => $task['urgency'],
                    'impact'                => $task['impact'],
                    'priority'              => $task['priority'],
                    'time_worked'           => $time_worked['display_value'],
                    'work_notes'            => $work_notes['display_value'],
                    'comments'              => $comments['display_value'],
                    'reassignment_count'    => $task['reassignment_count'],
                    'modified_count'        => $task['sys_mod_count'],
                    'cause_code'            => $cause_code['display_value'],
                    'data'                  => \Metaclassing\Utility::encodeJson($task),
                ]);

                $taskmodel->save();

                // touch task model to update the 'updated_at' timestamps in case nothing was changed
                $taskmodel->touch();

                Log::info('security task updated: '.$task['number']);
            } else {
                Log::info('creating new security task: '.$task['number']);

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

                $new_task = new ServiceNowSecurityTask();

                $new_task->task_id = $task['number'];
                $new_task->created_on = $task['sys_created_on'];
                $new_task->created_by = $task['sys_created_by'];
                $new_task->sys_id = $task['sys_id'];
                $new_task->class_name = $task['sys_class_name'];
                $new_task->parent = $parent['display_value'];
                $new_task->active = $task['active'];
                $new_task->updated_on = $updated_on['display_value'];
                $new_task->updated_by = $updated_by['display_value'];
                $new_task->opened_at = $task['opened_at'];
                $new_task->opened_by = $opened_by['display_value'];
                $new_task->closed_at = $closed_at['display_value'];
                $new_task->closed_by = $closed_by['display_value'];
                $new_task->close_notes = $close_notes['display_value'];
                $new_task->initial_assignment_group = $initial_assign_group['display_value'];
                $new_task->assignment_group = $assign_group['display_value'];
                $new_task->assigned_to = $assigned_to['display_value'];
                $new_task->state = $task['state'];
                $new_task->urgency = $task['urgency'];
                $new_task->impact = $task['impact'];
                $new_task->priority = $task['priority'];
                $new_task->time_worked = $time_worked['display_value'];
                $new_task->short_description = $task['short_description'];
                $new_task->description = $task['description'];
                $new_task->work_notes = $work_notes['display_value'];
                $new_task->comments = $comments['display_value'];
                $new_task->reassignment_count = $task['reassignment_count'];
                $new_task->district = $district['display_value'];
                $new_task->company = $company['display_value'];
                $new_task->department = $department['display_value'];
                $new_task->modified_count = $task['sys_mod_count'];
                $new_task->location = $location['display_value'];
                $new_task->cause_code = $cause_code['display_value'];
                $new_task->data = \Metaclassing\Utility::encodeJson($task);

                $new_task->save();
            }
        }
        */

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
                $some_date['display_value'] = $data;

                return $some_date;
            }
        } else {
            /*
            * otherwise if data is null then create and set the key
            * 'display_value' to the literal string 'null' and return it
            */
            $data['display_value'] = null;

            return $data;
        }
    }
}
