<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\ServiceNow\ServiceNowIncident;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSecurityIncidents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:securityincidents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new security incidents';

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
         * [1] Get security incidents
         */

        Log::info(PHP_EOL.PHP_EOL.'****************************************'.PHP_EOL.'* Starting security incidents crawler! *'.PHP_EOL.'****************************************');

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/servicenow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url to incidents table and add necessary query params
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/incident?sysparm_display_value=true&assignment_group=IM%20SEC%20-%20Security';

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
        file_put_contents(storage_path('app/responses/security_incidents.dump'), $json_response);

        // JSON decode response
        $response = \Metaclassing\Utility::decodeJson($json_response);

        // grab the data we care about and tell the world how many incidents we have
        $incidents = $response['result'];
        Log::info('total security incident count: '.count($incidents));

        $security_incidents = [];

        foreach ($incidents as $incident) {
            // handle null values for these particular fields this is not very pretty, but it works
            $resolved_by = $this->handleNull($incident['resolved_by']);
            $assigned_to = $this->handleNull($incident['assigned_to']);
            $district = $this->handleNull($incident['u_district_name']);
            $department = $this->handleNull($incident['department']);
            $company = $this->handleNull($incident['company']);
            $caller_id = $this->handleNull($incident['caller_id']);
            $initial_assignment_group = $this->handleNull($incident['u_initial_assignment_group']);
            $cmdb_ci = $this->handleNull($incident['cmdb_ci']);
            $assignment_group = $this->handleNull($incident['assignment_group']);
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
            $rfc = $this->handleNull($incident['rfc']);
            $associated_outage = $this->handleNull($incident['u_associated_outage']);

            $created_on_pieces = explode(' ', $incident['sys_created_on']);
            $created_on_date = $created_on_pieces[0].'T'.$created_on_pieces[1];

            $opened_at_pieces = explode(' ', $incident['opened_at']);
            $opened_at_date = $opened_at_pieces[0].'T'.$opened_at_pieces[1];

            if ($resolved_at['display_value']) {
                $resolved_at_pieces = explode(' ', $resolved_at['display_value']);
                $resolved_at['display_value'] = $resolved_at_pieces[0].'T'.$resolved_at_pieces[1];
            } else {
                $resolved_at['display_value'] = null;
            }

            if ($updated_on['display_value']) {
                $updated_on_pieces = explode(' ', $updated_on['display_value']);
                $updated_on['display_value'] = $updated_on_pieces[0].'T'.$updated_on_pieces[1];
            } else {
                $updated_on['display_value'] = null;
            }

            if ($closed_at['display_value']) {
                $closed_at_pieces = explode(' ', $closed_at['display_value']);
                $closed_at['display_value'] = $closed_at_pieces[0].'T'.$closed_at_pieces[1];
            } else {
                $closed_at['display_value'] = null;
            }

            $security_incidents[] = [
                'parent_incident'               => $incident['parent_incident'],
                'work_notes'                    => $incident['work_notes'],
                'business_service'              => $incident['business_service'],
                'reassignment_count'            => $incident['reassignment_count'],
                'u_case_task'                   => $incident['u_case_task'],
                'u_task_preferred_contact'      => $incident['u_task_preferred_contact'],
                'u_project_ref'                 => $project_ref['display_value'],
                'child_incidents'               => $incident['child_incidents'],
                'resolved_at'                   => $resolved_at['display_value'],
                'expected_start'                => $incident['expected_start'],
                'u_district_name'               => $district['display_value'],
                'parent'                        => $parent['display_value'],
                'u_alternative_contact'         => $alternative_contact['display_value'],
                'location'                      => $location['display_value'],
                'urgency'                       => $incident['urgency'],
                'assigned_to'                   => $assigned_to['display_value'],
                'follow_up'                     => $incident['follow_up'],
                'number'                        => $incident['number'],
                'knowledge'                     => $incident['knowledge'],
                'order'                         => $incident['order'],
                'opened_by'                     => $opened_by['display_value'],
                'service_offering'              => $incident['service_offering'],
                'upon_approval'                 => $incident['upon_approval'],
                'time_worked'                   => $incident['time_worked'],
                'u_cause_code'                  => $cause_code['display_value'],
                'sys_class_name'                => $incident['sys_class_name'],
                'contact_type'                  => $incident['contact_type'],
                'watch_list'                    => $incident['watch_list'],
                'u_impacted_services'           => $incident['u_impacted_services'],
                'upon_reject'                   => $incident['upon_reject'],
                'business_stc'                  => $incident['business_stc'],
                'u_reassignment_count_non_kss'  => $incident['u_reassignment_count_non_kss'],
                'impact'                        => $incident['impact'],
                'sys_id'                        => $incident['sys_id'],
                'problem_id'                    => $problem_id['display_value'],
                'department'                    => $department['display_value'],
                'skills'                        => $incident['skills'],
                'description'                   => $incident['description'],
                'short_description'             => $incident['short_description'],
                'sys_updated_on'                => $updated_on['display_value'],
                'activity_due'                  => $incident['activity_due'],
                'state'                         => $incident['state'],
                'category'                      => $incident['category'],
                'sys_domain'                    => $sys_domain['display_value'],
                'active'                        => $incident['active'],
                'business_duration'             => $incident['business_duration'],
                'sys_updated_by'                => $updated_by['display_value'],
                'work_end'                      => $incident['work_end'],
                'rfc'                           => $rfc['display_value'],
                'u_commented_by_assigned_tech'  => $incident['u_commented_by_assigned_tech'],
                'correlation_display'           => $incident['correlation_display'],
                'group_list'                    => $incident['group_list'],
                'close_code'                    => $incident['close_code'],
                'sla_due'                       => $incident['sla_due'],
                'resolved_by'                   => $resolved_by['display_value'],
                'closed_by'                     => $closed_by['display_value'],
                'sys_mod_count'                 => $incident['sys_mod_count'],
                'priority'                      => $incident['priority'],
                'correlation_id'                => $incident['correlation_id'],
                'u_attached_knowledge_stream'   => $incident['u_attached_knowledge_stream'],
                'u_hr_case'                     => $incident['u_hr_case'],
                'caused_by'                     => $incident['caused_by'],
                'work_start'                    => $incident['work_start'],
                'closed_at'                     => $closed_at['display_value'],
                'calendar_stc'                  => $incident['calendar_stc'],
                'u_survey_requested'            => $incident['u_survey_requested'],
                'cmdb_ci'                       => $cmdb_ci['display_value'],
                'u_internal_name'               => $internal_name['display_value'],
                'u_outage'                      => $incident['u_outage'],
                'u_last_updated_date'           => $incident['u_last_updated_date'],
                'close_notes'                   => $incident['close_notes'],
                'additional_assignee_list'      => $incident['additional_assignee_list'],
                'reopen_count'                  => $incident['reopen_count'],
                'work_notes_list'               => $incident['work_notes_list'],
                'u_initiatives'                 => $incident['u_initiatives'],
                'comments_and_work_notes'       => $incident['comments_and_work_notes'],
                'company'                       => $company['display_value'],
                'escalation'                    => $incident['escalation'],
                'u_assignment_group_changed'    => $incident['u_assignment_group_changed'],
                'opened_at'                     => $opened_at_date,
                'sys_created_on'                => $created_on_date,
                'assignment_group'              => $assignment_group['display_value'],
                'comments'                      => $incident['comments'],
                'sys_created_by'                => $incident['sys_created_by'],
                'severity'                      => $incident['severity'],
                'sys_tags'                      => $incident['sys_tags'],
                'u_sub_state'                   => $incident['u_sub_state'],
                'u_b_phone_contact'             => $incident['u_b_phone_contact'],
                'u_email_contact'               => $incident['u_email_contact'],
                'u_m_phone_contact'             => $incident['u_m_phone_contact'],
                'user_input'                    => $incident['user_input'],
                'u_business_services'           => $incident['u_business_services'],
                'approval_history'              => $incident['approval_history'],
                'made_sla'                      => $incident['made_sla'],
                'u_auto_close_date'             => $incident['u_auto_close_date'],
                'calendar_duration'             => $incident['calendar_duration'],
                'subcategory'                   => $incident['subcategory'],
                'u_followup_outlook_notify'     => $incident['u_followup_outlook_notify'],
                'approval'                      => $incident['approval'],
                'incident_state'                => $incident['incident_state'],
                'u_converted_from_task'         => $converted_from_task['display_value'],
                'u_vendor_name_task'            => $incident['u_vendor_name_task'],
                'approval_set'                  => $incident['approval_set'],
                'u_associated_outage'           => $associated_outage['display_value'],
                'u_initial_assignment_group'    => $initial_assignment_group['display_value'],
                'caller_id'                     => $caller_id['display_value'],
                'due_date'                      => $incident['due_date'],
                'notify'                        => $incident['notify'],
            ];
        }

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/security_incidents_collection.json'), \Metaclassing\Utility::encodeJson($security_incidents));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($security_incidents as $incident) {
            $result = $producer->send([
                [
                    'topic' => 'servicenow_security_incidents',
                    'value' => \Metaclassing\Utility::encodeJson($incident),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$incident['number']);
            }
        }

        /*
        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($security_incidents as $incident) {
            $url = 'http://10.243.32.36:9200/security_incidents/security_incidents/'.$incident['sys_id'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $incident,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('Security incident was successfully inserted into ES: '.$incident['sys_id']);
            } else {
                Log::error('Something went wrong inserting Security incident: '.$incident['sys_id']);
                die('Something went wrong inserting Security incident: '.$incident['sys_id'].PHP_EOL);
            }
        }
        */

        /*
         * [2] Process security incidents into database
         */

        /*
        Log::info(PHP_EOL.'******************************************'.PHP_EOL.'* Starting security incident processing! *'.PHP_EOL.'******************************************');

        // cycle through security incidents and add the new ones
        foreach ($incidents as $incident) {
            // try to find existing record with matching sys_id
            $exists = ServiceNowIncident::where('sys_id', $incident['sys_id'])->value('id');

            // if the incident already exists give it an update and a touch and move on
            if ($exists) {
                // handle null values
                $closed_at = $this->handleNull($incident['closed_at']);
                $updated_on = $this->handleNull($incident['sys_updated_on']);
                $assign_group = $this->handleNull($incident['assignment_group']);
                $resolved_by = $this->handleNull($incident['resolved_by']);
                $assigned_to = $this->handleNull($incident['assigned_to']);
                $resolved_at = $this->handleNull($incident['resolved_at']);

                // get incident model and update the fields that could have changed
                $incidentmodel = ServiceNowIncident::find($exists);
                $incidentmodel->update([
                    'closed_at'             => $closed_at['display_value'],
                    'updated_on'            => $updated_on['display_value'],
                    'assignment_group'      => $assign_group['display_value'],
                    'resolved_by'           => $resolved_by['display_value'],
                    'assigned_to'           => $assigned_to['display_value'],
                    'resolved_at'           => $resolved_at['display_value'],
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

                Log::info('security incident updated: '.$incident['number']);
            } else {
                // otherwise, create a new security incident record
                Log::info('creating new security incident: '.$incident['number']);

                // handle null values for these particular fields
                $resolved_by = $this->handleNull($incident['resolved_by']);
                $department = $this->handleNull($incident['department']);
                $assigned_to = $this->handleNull($incident['assigned_to']);
                $district = $this->handleNull($incident['u_district_name']);
                $caller_id = $this->handleNull($incident['caller_id']);
                $initial_assign_group = $this->handleNull($incident['u_initial_assignment_group']);
                $cmdb = $this->handleNull($incident['cmdb_ci']);
                $assign_group = $this->handleNull($incident['assignment_group']);
                $opened_by = $this->handleNull($incident['opened_by']);
                $closed_at = $this->handleNull($incident['closed_at']);
                $updated_on = $this->handleNull($incident['sys_updated_on']);
                $resolved_at = $this->handleNull($incident['resolved_at']);

                // create the new incident record
                $new_incident = new ServiceNowIncident();

                $new_incident->incident_id = $incident['number'];
                $new_incident->opened_at = $incident['opened_at'];
                $new_incident->closed_at = $closed_at['display_value'];
                $new_incident->state = $incident['incident_state'];
                $new_incident->duration = $incident['business_duration'];
                $new_incident->initial_assignment_group = $initial_assign_group['display_value'];
                $new_incident->sys_id = $incident['sys_id'];
                $new_incident->time_worked = $incident['time_worked'];
                $new_incident->reopen_count = $incident['reopen_count'];
                $new_incident->urgency = $incident['urgency'];
                $new_incident->impact = $incident['impact'];
                $new_incident->severity = $incident['severity'];
                $new_incident->priority = $incident['priority'];
                $new_incident->email_contact = $incident['u_email_contact'];
                $new_incident->description = $incident['description'];
                $new_incident->district = $district['display_value'];
                $new_incident->updated_on = $updated_on['display_value'];
                $new_incident->active = $incident['active'];
                $new_incident->assignment_group = $assign_group['display_value'];
                $new_incident->caller_id = $caller_id['display_value'];
                $new_incident->department = $department['display_value'];
                $new_incident->reassignment_count = $incident['reassignment_count'];
                $new_incident->short_description = $incident['short_description'];
                $new_incident->resolved_by = $resolved_by['display_value'];
                $new_incident->calendar_duration = $incident['calendar_duration'];
                $new_incident->assigned_to = $assigned_to['display_value'];
                $new_incident->resolved_at = $resolved_at['display_value'];
                $new_incident->cmdb_ci = $cmdb['display_value'];
                $new_incident->opened_by = $opened_by['display_value'];
                $new_incident->escalation = $incident['escalation'];
                $new_incident->modified_count = $incident['sys_mod_count'];
                $new_incident->data = \Metaclassing\Utility::encodeJson($incident);

                $new_incident->save();
            }
        }
        */

        Log::info('* Completed ServiceNow security incidents! *');
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
