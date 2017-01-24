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
        $response = $crawler->get($url);

        // dump response to file
        file_put_contents(storage_path('app/responses/security_tasks.dump'), $response);

        // JSON decode response and extract result data
        $security_tasks = json_decode($response, true);

        $tasks = $security_tasks['result'];
        Log::info('total security tasks count: '.count($tasks));

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/security_tasks_collection.json'), \Metaclassing\Utility::encodeJson($tasks));

        /*
         * [2] Process security tasks into database
         */

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
            $data['display_value'] = 'null';

            return $data;
        }
    }
}
