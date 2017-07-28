<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\ServiceNow\ServiceNowIdmIncident;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetIDMIncidents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:idmincidents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new IDM incidents';

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
         * [1] Get all IDM incidents email
         */

        Log::info(PHP_EOL.PHP_EOL.'***********************************'.PHP_EOL.'* Starting IDM incidents crawler! *'.PHP_EOL.'***********************************');

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/servicenow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url to incidents table and add necessary query params
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/incident?sysparm_display_value=true&assignment_group=KTG%20-%20Identity%20Management';

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
        file_put_contents(storage_path('app/responses/idm_incidents.dump'), $response);

        // JSON decode response
        $content = \Metaclassing\Utility::decodeJson($response);

        // grab the data we care about and tell the world how many incidents we have
        $incidents = $content['result'];
        Log::info('total IDM incident count: '.count($incidents));

        $idm_incidents = [];

        foreach ($incidents as $incident) {
            // handle null values for these particular fields this is not very pretty, but it works
            $resolved_by = $this->handleNull($incident['resolved_by']);
            $assigned_to = $this->handleNull($incident['assigned_to']);
            $district = $this->handleNull($incident['u_district_name']);
            $department = $this->handleNull($incident['department']);
            $company = $this->handleNull($incident['company']);
            $caller_id = $this->handleNull($incident['caller_id']);
            $initial_assign_group = $this->handleNull($incident['u_initial_assignment_group']);
            $cmdb_ci = $this->handleNull($incident['cmdb_ci']);
            $assign_group = $this->handleNull($incident['assignment_group']);
            $opened_by = $this->handleNull($incident['opened_by']);
            $closed_at = $this->handleNull($incident['closed_at']);
            $closed_by = $this->handleNull($incident['closed_by']);
            $updated_on = $this->handleNull($incident['sys_updated_on']);
            $updated_by = $this->handleNull($incident['sys_updated_by']);
            $resolved_at = $this->handleNull($incident['resolved_at']);
            $cause_code = $this->handleNull($incident['u_cause_code']);
            $location = $this->handleNull($incident['location']);
            $parent = $this->handleNull($incident['parent']);
            $project_ref = $this->handleNull($incident['u_project_ref']);
            $comments = $this->handleNull($incident['comments']);
            $sys_domain = $this->handleNull($incident['sys_domain']);
            $internal_name = $this->handleNull($incident['u_internal_name']);
            $converted_from_task = $this->handleNUll($incident['u_converted_from_task']);
            $problem_id = $this->handleNull($incident['problem_id']);
            $alternative_contact = $this->handleNull($incident['u_alternative_contact']);

            $created_on_pieces = explode(' ', $incident['sys_created_on']);
            $created_on_date = $created_on_pieces[0].'T'.$created_on_pieces[1];

            $opened_at_pieces = explode(' ', $incident['opened_at']);
            $opened_at_date = $opened_at_pieces[0].'T'.$opened_at_pieces[1];

            if ($resolved_at['display_value']) {
                $resolved_at_pieces = explode(' ', $resolved_at['display_value']);
                $resolved_at['display_value'] = $resolved_at_pieces[0].'T'.$resolved_at_pieces[1];
            }

            if ($updated_on['display_value']) {
                $updated_on_pieces = explode(' ', $updated_on['display_value']);
                $updated_on['display_value'] = $updated_on_pieces[0].'T'.$updated_on_pieces[1];
            }

            if ($closed_at['display_value']) {
                $closed_at_pieces = explode(' ', $closed_at['display_value']);
                $closed_at['display_value'] = $closed_at_pieces[0].'T'.$closed_at_pieces[1];
            }

            $idm_incidents[] = [
                'u_task_preferred_contact'      => $incident['u_task_preferred_contact'],
                'time_worked'                   => $incident['time_worked'],
                'u_impacted_services'           => $incident['u_impacted_services'],
                'number'                        => $incident['number'],
                'due_date'                      => $incident['due_date'],
                'sys_updated_by'                => $updated_by['display_value'],
                'short_description'             => $incident['short_description'],
                'resolved_by'                   => $resolved_by['display_value'],
                'resolved_at'                   => $resolved_at['display_value'],
                'u_sub_state'                   => $incident['u_sub_state'],
                'u_survey_requested'            => $incident['u_survey_requested'],
                'work_end'                      => $incident['work_end'],
                'u_initial_assignment_group'    => $initial_assign_group['display_value'],
                'upon_reject'                   => $incident['upon_reject'],
                'additional_assignee_list'      => $incident['additional_assignee_list'],
                'priority'                      => $incident['priority'],
                'group_list'                    => $incident['group_list'],
                'sys_created_on'                => $created_on_date,
                'u_followup_outlook_notify'     => $incident['u_followup_outlook_notify'],
                'u_hr_case'                     => $incident['u_hr_case'],
                'upon_approval'                 => $incident['upon_approval'],
                'u_cause_code'                  => $cause_code['display_value'],
                'u_business_services'           => $incident['u_business_services'],
                'u_converted_from_task'         => $converted_from_task['display_value'],
                'u_m_phone_contact'             => $incident['u_m_phone_contact'],
                'reopen_count'                  => $incident['reopen_count'],
                'caller_id'                     => $caller_id['display_value'],
                'approval_history'              => $incident['approval_history'],
                'u_attached_knowledge_stream'   => $incident['u_attached_knowledge_stream'],
                'u_customer_action'             => $incident['u_customer_action'],
                'u_last_updated_date'           => $incident['u_last_updated_date'],
                'u_initiatives'                 => $incident['u_initiatives'],
                'business_service'              => $incident['business_service'],
                'calendar_duration'             => $incident['calendar_duration'],
                'location'                      => $location['display_value'],
                'opened_by'                     => $opened_by['display_value'],
                'rfc'                           => $incident['rfc'],
                'child_incidents'               => $incident['child_incidents'],
                'parent'                        => $parent['display_value'],
                'sys_tags'                      => $incident['sys_tags'],
                'closed_by'                     => $closed_by['display_value'],
                'caused_by'                     => $incident['caused_by'],
                'u_b_phone_contact'             => $incident['u_b_phone_contact'],
                'problem_id'                    => $problem_id['display_value'],
                'cmdb_ci'                       => $cmdb_ci['display_value'],
                'u_project_ref'                 => $project_ref['display_value'],
                'comments'                      => $comments['display_value'],
                'parent_incident'               => $incident['parent_incident'],
                'department'                    => $department['display_value'],
                'rejection_goto'                => $incident['rejection_goto'],
                'u_auto_close_date'             => $incident['u_auto_close_date'],
                'u_vendor_name'                 => $incident['u_vendor_name'],
                'sys_created_by'                => $incident['sys_created_by'],
                'urgency'                       => $incident['urgency'],
                'activity_due'                  => $incident['activity_due'],
                'assigned_to'                   => $assigned_to['display_value'],
                'comments_and_work_notes'       => $incident['comments_and_work_notes'],
                'knowledge'                     => $incident['knowledge'],
                'state'                         => $incident['state'],
                'category'                      => $incident['category'],
                'sys_id'                        => $incident['sys_id'],
                'calendar_stc'                  => $incident['calendar_stc'],
                'sla_due'                       => $incident['sla_due'],
                'u_assignment_group_changed'    => $incident['u_assignment_group_changed'],
                'work_start'                    => $incident['work_start'],
                'work_notes'                    => $incident['work_notes'],
                'business_duration'             => $incident['business_duration'],
                'opened_at'                     => $opened_at_date,
                'severity'                      => $incident['severity'],
                'correlation_display'           => $incident['correlation_display'],
                'subcategory'                   => $incident['subcategory'],
                'skills'                        => $incident['skills'],
                'notify'                        => $incident['notify'],
                'u_case_task'                   => $incident['u_case_task'],
                'follow_up'                     => $incident['follow_up'],
                'incident_state'                => $incident['incident_state'],
                'u_commented_by_assigned_tech'  => $incident['u_commented_by_assigned_tech'],
                'active'                        => $incident['active'],
                'u_district_name'               => $district['display_value'],
                'watch_list'                    => $incident['watch_list'],
                'company'                       => $company['display_value'],
                'approval'                      => $incident['approval'],
                'order'                         => $incident['order'],
                'closed_at'                     => $closed_at['display_value'],
                'sys_updated_on'                => $updated_on['display_value'],
                'contact_type'                  => $incident['contact_type'],
                'sys_class_name'                => $incident['sys_class_name'],
                'u_associated_outage'           => $incident['u_associated_outage'],
                'assignment_group'              => $assign_group['display_value'],
                'approval_set'                  => $incident['approval_set'],
                'u_vendor_name_task'            => $incident['u_vendor_name_task'],
                'sys_mod_count'                 => $incident['sys_mod_count'],
                'sys_domain'                    => $sys_domain['display_value'],
                'impact'                        => $incident['impact'],
                'u_internal_name'               => $internal_name['display_value'],
                'expected_start'                => $incident['expected_start'],
                'u_email_contact'               => $incident['u_email_contact'],
                'user_input'                    => $incident['user_input'],
                'service_offering'              => $incident['service_offering'],
                'escalation'                    => $incident['escalation'],
                'close_code'                    => $incident['close_code'],
                'close_notes'                   => $incident['close_notes'],
                'business_stc'                  => $incident['business_stc'],
                'work_notes_list'               => $incident['work_notes_list'],
                'u_reassignment_count_non_kss'  => $incident['u_reassignment_count_non_kss'],
                'u_outage'                      => $incident['u_outage'],
                'u_alternative_contact'         => $alternative_contact['display_value'],
                'correlation_id'                => $incident['correlation_id'],
                'reassignment_count'            => $incident['reassignment_count'],
                'description'                   => $incident['description'],
                'made_sla'                      => $incident['made_sla'],
            ];
        }

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($idm_incidents as $incident) {
            $url = 'http://10.243.32.36:9200/idm_incidents/idm_incidents/'.$incident['sys_id'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $incident,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('IDM incident was successfully inserted into ES: '.$incident['sys_id']);
            } else {
                Log::error('Something went wrong inserting IDM incident: '.$incident['sys_id']);
                die('Something went wrong inserting IDM incident: '.$incident['sys_id'].PHP_EOL);
            }
        }

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/idm_incidents_collection.json'), \Metaclassing\Utility::encodeJson($idm_incidents));

        /*
         * [2] Process IDM incidents into database
         */
        /*
        Log::info(PHP_EOL.'**************************************'.PHP_EOL.'* Starting IDM incidents processing! *'.PHP_EOL.'**************************************');

        // cycle through IDM incidents and add the new ones
        foreach ($idm_incidents as $incident) {
            // try to find existing record with matching sys_id
            $exists = ServiceNowIdmIncident::where('sys_id', $incident['sys_id'])->value('id');

            // if the incident already exists give it an update and a touch and move on
            if ($exists) {
                // get incident model and update the fields that could have changed
                $incidentmodel = ServiceNowIdmIncident::find($exists);
                $incidentmodel->update([
                    'closed_at'             => $incident['closed_at'],
                    'updated_on'            => $incident['sys_updated_on'],
                    'assignment_group'      => $incident['assignment_group'],
                    'resolved_by'           => $incident['resolved_by'],
                    'assigned_to'           => $incident['assigned_to'],
                    'resolved_at'           => $incident['resolved_at'],
                    'state'                 => $incident['incident_state'],
                    'duration'              => $incident['business_duration'],
                    'time_worked'           => $incident['time_worked'],
                    'reopen_count'          => $incident['reopen_count'],
                    'urgency'               => $incident['urgency'],
                    'impact'                => $incident['impact'],
                    'severity'              => $incident['severity'],
                    'priority'              => $incident['priority'],
                    'active'                => $incident['active'],
                    'reassignment_count'    => $incident['reassignment_count'],
                    'calendar_duration'     => $incident['calendar_duration'],
                    'escalation'            => $incident['escalation'],
                    'modified_count'        => $incident['sys_mod_count'],
                    'data'                  => \Metaclassing\Utility::encodeJson($incident),
                ]);

                $incidentmodel->save();

                // touch incident model to update the 'updated_at' timestamp in case nothing was changed
                $incidentmodel->touch();

                Log::info('IDM incident updated: '.$incident['number']);
            } else {
                // otherwise, create a new security incident record
                Log::info('creating new IDM incident: '.$incident['number']);

                // create the new incident record
                $new_incident = new ServiceNowIdmIncident();

                $new_incident->incident_id = $incident['number'];
                $new_incident->opened_at = $incident['opened_at'];
                $new_incident->closed_at = $incident['closed_at'];
                $new_incident->state = $incident['incident_state'];
                $new_incident->duration = $incident['business_duration'];
                $new_incident->initial_assignment_group = $incident['u_initial_assignment_group'];
                $new_incident->sys_id = $incident['sys_id'];
                $new_incident->time_worked = $incident['time_worked'];
                $new_incident->reopen_count = $incident['reopen_count'];
                $new_incident->urgency = $incident['urgency'];
                $new_incident->impact = $incident['impact'];
                $new_incident->severity = $incident['severity'];
                $new_incident->priority = $incident['priority'];
                $new_incident->email_contact = $incident['u_email_contact'];
                $new_incident->description = $incident['description'];
                $new_incident->district = $incident['u_district_name'];
                $new_incident->updated_on = $incident['sys_updated_on'];
                $new_incident->active = $incident['active'];
                $new_incident->assignment_group = $incident['assignment_group'];
                $new_incident->caller_id = $incident['caller_id'];
                $new_incident->department = $incident['department'];
                $new_incident->reassignment_count = $incident['reassignment_count'];
                $new_incident->short_description = $incident['short_description'];
                $new_incident->resolved_by = $incident['resolved_by'];
                $new_incident->calendar_duration = $incident['calendar_duration'];
                $new_incident->assigned_to = $incident['assigned_to'];
                $new_incident->resolved_at = $incident['resolved_at'];
                $new_incident->cmdb_ci = $incident['cmdb_ci'];
                $new_incident->opened_by = $incident['opened_by'];
                $new_incident->escalation = $incident['escalation'];
                $new_incident->modified_count = $incident['sys_mod_count'];
                $new_incident->data = \Metaclassing\Utility::encodeJson($incident);

                $new_incident->save();
            }
        }
        */

        Log::info('* IDM incidents completed! *'.PHP_EOL);
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
                * otherwise we're dealing with a date string, so set the value
                * of an array key named 'display_value' to the date string
                */
                $some_date['display_value'] = $data;

                return $some_date;
            }
        } else {
            /*
            * otherwise if data is null then create and set the array key
            * 'display_value' to an empty string and return it
            */
            $data['display_value'] = null;

            return $data;
        }
    }
}
