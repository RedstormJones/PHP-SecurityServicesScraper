<?php

namespace App\Console\Commands;

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
        $response = $crawler->get($url);

        // dump response to file
        file_put_contents(storage_path('app/responses/security_incidents.dump'), $response);

        // JSON decode response
        $security_incidents = \Metaclassing\Utility::decodeJson($response);

        // grab the data we care about and tell the world how many incidents we have
        $incidents = $security_incidents['result'];
        Log::info('total security incident count: '.count($incidents));

        // JSON encode and dump incident collection to file
        file_put_contents(storage_path('app/collections/security_incidents_collection.json'), \Metaclassing\Utility::encodeJson($incidents));

        /*
         * [2] Process security incidents into database
         */

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

                /*
                * handle null values for these particular fields
                * this is not very pretty, but it works
                */
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
