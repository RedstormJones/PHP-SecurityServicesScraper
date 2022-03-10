<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetTrapIncidents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:trapincidents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get incidents from Trap';

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
        Log::info('[GetTrapIncidents.php] Starting Poll');

        $date = Carbon::now()->toDateString();

        // get the LR OC webhookbeat uri
        $webhook_uri = getenv('WEBHOOK_URI');

        $auth_token = getenv('TRAP_TOKEN');

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/trap_api.txt');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // build auth header and add to crawler
        $headers = [
            'Authorization: '.$auth_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);



        # setup UTC datetime
        $created_after = Carbon::now()->subHour()->toIso8601ZuluString();
        Log::info('[GetTrapIncidents.php] created after date: '.$created_after);

        // setup url params and convert to &-delimited string
        $url_params = [
            'created_after' => $created_after
        ];
        $url_params_str = $this->postArrayToString($url_params);

        $trap_uri = 'https://trap.kiewitplaza.com';

        $json_response = $crawler->get($trap_uri.'/api/incidents?'.$url_params_str);
        file_put_contents(storage_path('app/responses/trap_api.response'), $json_response);

        // attempt to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            Log::error('[GetTrapIncidents.php] attempt to decode JSON response failed: '.$e->getMessage());
            die('[GetTrapIncidents.php] attempt to decode JSON response failed: '.$e->getMessage());
        }


        // cycle through each incident in the response
        foreach ($response as $incident) {
            if ($incident['state'] == "Closed") {

                foreach($incident['event_sources'] as $source) {
                    if ($source == "CLEAR") {
                        Log::info('[GetTrapIncidents.php] found CLEAR incident with Closed state: '.$incident['id']);

                        // instantiate the event sources string we're about to build
                        $event_sources_str = '';

                        // get the event sources array from the incident
                        $event_sources = $incident['event_sources'];

                        // if event sources count is greater than 1 then we need to loop through them
                        if (count($event_sources) > 1) {
                            // cycle through event sources and build event sources string, delimited with semicolons
                            foreach ($event_sources as $source) {
                                $event_sources_str .= $source.';';
                            }
                        } else {
                            // otherwise is event sources count is not greater than 1 then there must be just 1 so assign in to event sources string
                            $event_sources_str = $event_sources[0];
                        }

                        // grab the incident_field_values array from the incident
                        $field_values = $incident['incident_field_values'];

                        // instantiate the new field values array
                        $field_values_array = [];

                        // cycle through the old field values array and rebuild as array of key/value pairs
                        foreach ($field_values as $fv) {
                            $field_values_array += [
                                $fv['name'] => $fv['value']
                            ];
                        }

                        // if close_detail exists in the incident object then set action to its value 
                        if (array_key_exists('close_detail', $incident)) {
                            $action = $incident['close_detail'];
                        } else {
                            // otherwise set action to null
                            $action = null;
                        }

                        // instantiate these things first
                        $sender_email = null;
                        $recipient_email = null;
                        $subject = null;
                        $spam_reason = null;
                        $url_str = null;

                        $new_original_message = [];
                        $new_events_array = [];

                        // cycle through each event in the incident, and each email in each event
                        foreach ($incident['events'] as $event) {

                            $new_emails_array = [];

                            foreach ($event['emails'] as $email) {
                                // grab recipeint and sender objects
                                $recipient = $email['recipient'];
                                $sender = $email['sender'];

                                // check that recipient email is not trapabuse or spam@kiewit.com
                                if ($recipient['email'] != "trapabuse@kiewit.com" && $recipient['email'] != "spam@kiewit.com") {
                                    // set recipient, sender and subject accordingly
                                    $recipient_email = $recipient['email'];
                                    $sender_email = $sender['email'];
                                    $subject = $email['subject'];

                                    // grab email headers and get PP spam reason header
                                    $headers = $email['headers'];
                                    if (array_key_exists('X-Proofpoint-Spam-Reason', $headers)) {
                                        $spam_reason = $headers['X-Proofpoint-Spam-Reason'];
                                    }
                                    
                                    $urls = $email['urls'];
                                    if (count($urls) > 1) {
                                        /*foreach ($email['urls'] as $url) {
                                            $url_str .= $url.';';
                                        }*/

                                        $url_str = implode(';', $urls);
                                    } else {
                                        $url_str = $urls[0];
                                    }

                                    // rebuild emails array with only the important stuff
                                    $new_emails_array[] = [
                                        'messageId'     => $email['messageId'],
                                        'sender'        => $sender_email,
                                        'recipient'     => $recipient_email,
                                        'subject'       => $subject,
                                        'headers'       => [
                                            'X-Proofpoint-Spam-Reason'  => $spam_reason
                                        ]
                                    ];

                                    break;
                                }
                            }

                            // rebuild events array with new email array
                            $new_events_array[] = [
                                'eventId'           => $event['id'],
                                'alertType'         => $event['alertType'],
                                'severity'          => $event['severity'],
                                'source'            => $event['source'],
                                'state'             => $event['state'],
                                'attackDirection'   => $event['attackDirection'],
                                'received'          => $event['received'],
                                'emails'            => $new_emails_array
                            ];

                            // if we have botha  sender and recipient email then break from processing events
                            if ($sender_email && $recipient_email) {
                                break;
                            }
                        }

                        // build new original message
                        $new_original_message = [
                            'incident_id'               => $incident['id'],
                            'summary'                   => $incident['summary'],
                            'description'               => $incident['description'],
                            'score'                     => $incident['score'],
                            'state'                     => $incident['state'],
                            'created_at'                => $incident['created_at'],
                            'updated_at'                => $incident['updated_at'],
                            'closed_at'                 => $incident['closed_at'],
                            'close_summary'             => $incident['close_summary'],
                            'close_detail'              => $incident['close_detail'],
                            'event_count'               => $incident['event_count'],
                            'false_positive_count'      => $incident['false_positive_count'],
                            'event_sources'             => $incident['event_sources'],
                            'users'                     => $incident['users'],
                            'assignee'                  => $incident['assignee'],
                            'team'                      => $incident['team'],
                            'incident_field_values'     => $incident['incident_field_values'],
                            'events'                    => $new_events_array,
                            'comments'                  => $incident['comments'],
                            'quarantine_results'        => $incident['quarantine_results'],
                            'successful_quarantines'    => $incident['successful_quarantines'],
                            'failed_quarantines'        => $incident['failed_quarantines'],
                            'pending_quarantines'       => $incident['pending_quarantines']
                        ];


                        // build trap incident log to send to whb
                        $incident_log = [
                            'vmid'                      => $incident['id'],
                            'result'                    => $field_values_array['Abuse Disposition'],
                            'severity'                  => $field_values_array['Severity'],
                            'classification'            => $field_values_array['Classification'],
                            'size'                      => $incident['successful_quarantines'],
                            'rate'                      => $incident['failed_quarantines'],
                            'processid'                 => $incident['pending_quarantines'],
                            'status'                    => $incident['state'],
                            'action'                    => $action,
                            'vendorinfo'                => $event_sources_str,
                            'sender'                    => $sender_email,
                            'recipient'                 => $recipient_email,
                            'subject'                   => $subject,
                            'reason'                    => $spam_reason,
                            'url'                       => $url_str,
                            'whsdp'                     => true,
                            'fullyqualifiedbeatname'    => 'webhookbeat-proofpoint-trap-incident',
                            'original_message'          => \Metaclassing\Utility::encodeJson($new_original_message)
                        ];

                        // JSON encode new trap incident log
                        $incident_log_json = \Metaclassing\Utility::encodeJson($incident_log);
                        file_put_contents(storage_path('app/output/trap/'.$date.'-incidents.log'), $incident_log_json, FILE_APPEND);

                        // post incident log to the LR OC
                        $webhook_response = $crawler->post($webhook_uri, '', $incident_log_json);
                        file_put_contents(storage_path('app/responses/trap_incidents_to_webhook.response'), $webhook_response);
                    }
                }
            }
        }

        Log::info('[GetTrapIncidents.php] ProofPoint Trap incidents command completed!');

    }




    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];
        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.$value;
        }

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }



}
