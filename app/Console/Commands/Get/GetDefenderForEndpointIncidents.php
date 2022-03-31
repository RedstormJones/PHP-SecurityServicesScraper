<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetDefenderForEndpointIncidents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:endpointincidents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Defender for Endpoint incidents.';

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
        Log::info('[GetDefenderForEndpointIncidents.php] Starting Defender for Endpoints Incidents Poll!');


        $output_date = Carbon::now()->toDateString();

        // get values from environment file
        $token_endpoint = getenv('MS_OAUTH_DEFENDER_TOKEN_ENDPOINT');
        $app_id = getenv('LR_ALERT_API_ID');
        $app_key = getenv('LR_ALERT_API_KEY');

        $post_data = 'resource=https://api.security.microsoft.com&client_id='.$app_id.'&client_secret='.$app_key.'&grant_type=client_credentials';

        $cookiejar = storage_path('app/cookies/def_for_endpoint_incidents_cookie.txt');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // post to token endpoint
        Log::info('[GetDefenderForEndpointIncidents.php] posting for access token...');
        $json_response = $crawler->post($token_endpoint, '', $post_data);
        file_put_contents(storage_path('app/responses/deffore_incidents_token_response.json'), $json_response);

        try {
            // get access token from response
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $access_token = $response['access_token'];
            Log::info('[GetDefenderForEndpointIncidents.php] got access token...');
        } catch (\Exception $e) {
            Log::error('[GetDefenderForEndpointIncidents.php] ERROR: failed to get access token: '.$e->getMessage());
            die('[GetDefenderForEndpointIncidents.php] ERROR: failed to get access token: '.$e->getMessage().PHP_EOL);
        }


        //$created_after = Carbon::now()->subHour()->toIso8601ZuluString();
        //$created_after = Carbon::now()->subHours(24)->toIso8601ZuluString();
        $created_after = Carbon::now()->subMinutes(5)->toIso8601ZuluString();
        Log::info('[GetDefenderForEndpointIncidents.php] created after datetime: '.$created_after);

        // re-instantiate crawler to get incidents from the Defender for Endpoint Incidents API
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth headers and apply to crawler
        $headers = [
            'Authorization: Bearer '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // setup url params
        $url_params = [
            "\$filter"   => "createdTime+ge+".$created_after."+and+status+eq+'Active'"
        ];
        $url_params_str = $this->postArrayToString($url_params);

        // send request, capture response and dump to file
        $json_response = $crawler->get('https://api.security.microsoft.com/api/incidents?'.$url_params_str);
        file_put_contents(storage_path('app/responses/deffore_incidents.response'), $json_response);

        $deffore_incidents = [];

        // attempt to decode JSON response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            foreach ($response['value'] as $value) {
                $deffore_incidents[] = $value;
            }
            Log::info('[GetDefenderForEndpointIncidents.php] received ['.count($response['value']).'] incidents from Defender for Endpoint API');
        } catch (\Exception $e) {
            Log::error('[GetDefenderForEndpointIncidents.php] ERROR: failed to decode JSON response: '.$e->getMessage());
            die('[GetDefenderForEndpointIncidents.php] ERROR: failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }

        while (array_key_exists('@odata.nextLink', $response)) {
            // get the next page uri from the response
            $next_link = $response['@odata.nextLink'];

            // send request, capture response and dump to file
            $json_response = $crawler->get($next_link);
            file_put_contents(storage_path('app/responses/deffore_incidents.response'), $json_response);

            // attempt to decode JSON response
            try {
                $response = \Metaclassing\Utility::decodeJson($json_response);
                foreach ($response['value'] as $value) {
                    $deffore_incidents[] = $value;
                }
                Log::info('[GetDefenderForEndpointIncidents.php] received ['.count($response['value']).'] incidents from Defender for Endpoint API');
            } catch (\Exception $e) {
                Log::error('[GetDefenderForEndpointIncidents.php] ERROR: failed to decode JSON response: '.$e->getMessage());
                die('[GetDefenderForEndpointIncidents.php] ERROR: failed to decode JSON response: '.$e->getMessage().PHP_EOL);
            }
        }

        // dump incidents to file
        file_put_contents(storage_path('app/collections/deffore_incidents.json'), \Metaclassing\Utility::encodeJson($deffore_incidents));


        // re-instantiate crawler to post logs to the OC webhookbeat
        $crawler = new \Crawler\Crawler($cookiejar);

        // build headers and set to crawler object
        $headers = [
            'Content-Type: application/json'
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // get OC webhookbeat uri from environment file
        $webhook_uri = getenv('WEBHOOK_URI');


        // cycle through incidents and create the incident log
        foreach ($deffore_incidents as $incident) {
            /*
                Starred(*) properties need to be included in any sub-logs we create

                'Incident'          => objecttype
                *incidentId         => serialnumber
                incidentUri         => vendorinfo
                createdTime         => timestamp.iso1806
                classification      => reason
                status              => status
                severity            => severity
            */

            $oc_log = [
                // these key/value pairs should persist down into sub-logs
                'objecttype'                => 'Incident',
                'tag1'                      => $incident['classification'],
                'serialnumber'              => $incident['incidentId'],
                'vendorinfo'                => urldecode($incident['incidentUri']),
                'timestamp.iso1806'         => $incident['createdTime'],
                'reason'                    => $incident['classification'],
                'status'                    => $incident['status'],
                'severity'                  => $incident['severity'],
                'original_message'          => $incident,
                'whsdp'                     => True,
                'fullyqualifiedbeatname'    => 'webhookbeat-defender-incident',

                // create these object fields to use later when create sub-logs
                'object'                    => null,
                'objectname'                => null,
                'hash'                      => null,
                'policy'                    => null,
                'result'                    => null,
                'url'                       => null,
                'useragent'                 => null,
                'responsecode'              => null,
                'subject'                   => null,
                'version'                   => null,
                'command'                   => null,
                'action'                    => null,
                'sessiontype'               => null,
                'process'                   => null,
                'processid'                 => null,
                'parentprocessid'           => null,
                'parentprocessname'         => null,
                'quantity'                  => null,
                'amount'                    => null,
                'size'                      => null,
                'rate'                      => null,
                'vmid'                      => null,
                'threatname'                => null,
                'threatid'                  => null,
                'cve'                       => null,
                'sip'                       => null,
                'dip'                       => null,
                'sname'                     => null,
                'dname'                     => null,
                'login'                     => null,
                'account'                   => null,
                'sender'                    => null,
                'recipient'                 => null,
                'domainorigin'              => null,
                'domainimpacted'            => null,
                'sport'                     => null,
                'dport'                     => null,
                'group'                     => null
            ];

            file_put_contents(storage_path('app/output/defender/incidents/'.$output_date.'-incidents.log'), \Metaclassing\Utility::encodeJson($oc_log).PHP_EOL, FILE_APPEND);

            // post incident log to OC webhookbeat
            $webhook_response = $crawler->post($webhook_uri, '', \Metaclassing\Utility::encodeJson($oc_log));
            file_put_contents(storage_path('app/responses/deffore_webhook.response'), $webhook_response);

            // be easy on the OC
            sleep(1);


            // clear out the key/value pairs specific to the incident log
            $oc_log['vendorinfo'] = null;
            $oc_log['timestamp.iso1806'] = null;
            $oc_log['reason'] = null;
            $oc_log['status'] = null;
            $oc_log['severity'] = null;
            $oc_log['original_message'] = null;



            $alerts = $incident['alerts'];

            // set the quantity field to the count of alerts in the incident
            $oc_log['quantity']  = count($alerts);
            Log::info('[GetDefenderForEndpointIncidents.php] found '.count($alerts).' alerts for incident '.$incident['incidentId']);

            // instantiate the number of the alert we are currently processing
            $alert_current = 0;

            // cycle through incident alerts and build incident alert logs
            foreach ($alerts as $alert) {
                /*
                    'Alert'             => objectype
                    *incidentId         => serialnumber
                    *alertId            => vmid
                    creationTime        => timestamp.iso1806
                    title               => subject
                    description         => vendorinfo
                    category            => tag2
                    category            => result
                    status              => status
                    severity            => severity
                    classification      => reason
                    detectionSource     => tag3
                    detectionSource     => parentprocessname
                    threatFamilyName    => threatname
                */
                
                $oc_log['objecttype'] = 'Alert';
                $oc_log['amount'] = ++$alert_current;
                $oc_log['vmid'] = $alert['alertId'];
                $oc_log['timestamp.iso1806'] = $alert['creationTime'];
                $oc_log['subject'] = $alert['title'];
                $oc_log['vendorinfo'] = $alert['description'];
                $oc_log['tag2'] = $alert['category'];
                $oc_log['policy'] = $alert['category'];
                $oc_log['status'] = $alert['status'];
                $oc_log['severity'] = $alert['severity'];
                $oc_log['reason'] = $alert['classification'];
                $oc_log['parentprocessname'] = $alert['detectionSource'];
                $oc_log['tag3'] = $alert['detectionSource'];
                $oc_log['threatname'] = $alert['threatFamilyName'];


                file_put_contents(storage_path('app/output/defender/alerts/'.$output_date.'-alerts.json'), \Metaclassing\Utility::encodeJson($oc_log).PHP_EOL, FILE_APPEND);

                // post alert log to the OC webhookbeat and dump response to file
                $webhook_response = $crawler->post($webhook_uri, '', \Metaclassing\Utility::encodeJson($oc_log));
                file_put_contents(storage_path('app/responses/deffore_webhook.response'), $webhook_response);

                // be easy on the OC
                sleep(1);

                // clear out the key/value pairs specific to the alert log
                $oc_log['timestamp.iso1806'] = null;
                $oc_log['subject'] = null;
                $oc_log['vendorinfo'] = null;
                $oc_log['tag2'] = null;
                $oc_log['policy'] = null;
                $oc_log['status'] = null;
                $oc_log['severity'] = null;
                $oc_log['reason'] = null;
                $oc_log['parentprocessname'] = null;
                $oc_log['tag3'] = null;
                $oc_log['threatname'] = null;



                $devices = $alert['devices'];

                // set the quantity field to the count of devices in the alert
                $oc_log['quantity'] = count($devices);
                Log::info('[GetDefenderForEndpointIncidents.php] found '.count($devices).' devices for alert '.$alert['alertId']);

                // instantiate the number of the device we are currently processing
                $device_current = 0;

                // cycle through entities and create alert entities logs
                foreach ($devices as $device) {
                    /*
                        objecttype      => 'Device'
                        *serialnumber   => incidentId
                        *vmid           => alertId
                        object          => mdatpDeviceId
                        objectname      => aadDeviceId
                        sname           => deviceDnsName
                        dname           => deviceDnsName
                        useragent       => osPlatform
                        status          => healthStatus
                        severity        => riskScore
                        group           => rbacGroupName
                        tag4            => tags[0]
                        vendorinfo      => defenderAvStatus
                        policy          => onboardingStatus
                    */

                    $device_tags = $device['tags'];
                    if (count($device_tags)) {
                        $oc_log['tag4'] = $device_tags[0];
                    }

                    $oc_log['objecttype'] = 'Device';
                    $oc_log['amount'] = ++$device_current;
                    $oc_log['object'] = $device['mdatpDeviceId'];
                    $oc_log['objectname'] = $device['aadDeviceId'];
                    $oc_log['sname'] = $device['deviceDnsName'];
                    $oc_log['dname'] = $device['deviceDnsName'];
                    $oc_log['useragent'] = $device['osPlatform'];
                    $oc_log['status'] = $device['healthStatus'];
                    $oc_log['severity'] = $device['riskScore'];
                    $oc_log['group'] = $device['rbacGroupName'];
                    $oc_log['vendorinfo'] = $device['defenderAvStatus'];
                    $oc_log['policy'] = $device['onboardingStatus'];

                    file_put_contents(storage_path('app/output/defender/devices/'.$output_date.'-devices.json'), \Metaclassing\Utility::encodeJson($oc_log).PHP_EOL, FILE_APPEND);

                    // post device log to the OC webhookbeat and dump response to file
                    $webhook_response = $crawler->post($webhook_uri, '', \Metaclassing\Utility::encodeJson($oc_log));
                    file_put_contents(storage_path('app/responses/deffore_webhook.response'), $webhook_response);

                    // be easy on the OC
                    sleep(1);

                    
                    // clear out the key/value pairs specific to the device log
                    $oc_log['object'] = null;
                    $oc_log['objectname'] = null;
                    $oc_log['sname'] = null;
                    $oc_log['dname'] = null;
                    $oc_log['useragent'] = null;
                    $oc_log['status'] = null;
                    $oc_log['severity'] = null;
                    $oc_log['group'] = null;
                    $oc_log['vendorinfo'] = null;
                    $oc_log['policy'] = null;

                    
                    $users = $device['loggedOnUsers'];

                    // set the quantity field to the count of logged on users in the device
                    $oc_log['quantity'] = count($users);
                    Log::info('[GetDefenderForEndpointIncidents.php] found '.count($users).' users for device '.$device_current.' in alert '.$alert['alertId']);

                    // instantiate the number of the user we are currently processing
                    $user_current = 0;

                    // cycle through users and create alert users logs
                    foreach ($users as $user) {
                        /*
                            objecttype      => 'User'
                            *serialnumber   => incidentId
                            *vmid           => alertId
                            login           => accountName
                            account         => accountName
                            domainimpacted  => domainName
                            domainorigin    => domainName
                        */

                        $oc_log['objecttype'] = 'User';
                        $oc_log['amount'] = ++$user_current;
                        $oc_log['account'] = $user['accountName'];
                        $oc_log['login'] = $user['accountName'];
                        $oc_log['domainimpacted'] = $user['domainName'];
                        $oc_log['domainorigin'] = $user['domainName'];

                        file_put_contents(storage_path('app/output/defender/users/'.$output_date.'-users.json'), \Metaclassing\Utility::encodeJson($oc_log).PHP_EOL, FILE_APPEND);

                        // post user log to the OC webhookbeat and dump response to file
                        $webhook_response = $crawler->post($webhook_uri, '', \Metaclassing\Utility::encodeJson($oc_log));
                        file_put_contents(storage_path('app/responses/deffore_webhook.response'), $webhook_response);

                        // be easy on the OC
                        sleep(1);


                        // clear out the key/value pairs specific to the user log
                        $oc_log['account'] = null;
                        $oc_log['login'] = null;
                        $oc_log['domainimpacted'] = null;
                        $oc_log['domainorigin'] = null;

                    } // end of device logged on users loop

                } // end of alert devices loop


                $entities = $alert['entities'];

                // set the quantity field to the count of entities in the alert
                $oc_log['quantity'] = count($entities);
                Log::info('[GetDefenderForEndpointIncidents.php] found '.count($entities).' entities for alert '.$alert['alertId']);

                // instantiate the number of the entity we are currently processing
                $entity_current = 0;

                // cycle through devices and create alert devices logs
                foreach ($entities as $entity) {
                    /*
                        objecttype          => 'Entity'
                        *serialnumber       => incidentId
                        *vmid               => alertId
                        sessiontype         => entityType
                        timestamp.iso1806   => evidenceCreationTime
                        result              => verdict
                        status              => remediationStatus
                    */
                    $oc_log['objecttype'] = 'Entity';
                    $oc_log['amount'] = ++$entity_current;
                    $oc_log['sessiontype'] = $entity['entityType'];
                    $oc_log['timestamp.iso1806'] = $entity['evidenceCreationTime'];
                    $oc_log['result'] = $entity['verdict'];
                    $oc_log['status'] = $entity['remediationStatus'];

                    // set fields specific to MailCluster entities
                    if ($entity['entityType'] == 'MailCluster') {
                        $oc_log['size'] = $entity['emailCount'];
                    }

                    // set fields specific to MailMessage entities
                    if ($entity['entityType'] == 'MailMessage') {
                        $oc_log['account'] = $entity['userPrincipalName'];
                        $oc_log['sender'] = $entity['sender'];
                        $oc_log['recipient'] = $entity['recipient'];

                        if (array_key_exists('subject', $entity)) {
                            $oc_log['subject'] = $entity['subject'];
                        }

                        if (array_key_exists('deliveryAction', $entity)) {
                            $oc_log['action'] = $entity['deliveryAction'];
                        }
                    }

                    // set fields specific to Mailbox entities
                    if ($entity['entityType'] == 'Mailbox') {
                        $oc_log['account'] = $entity['mailboxAddress'];
                        $oc_log['objectname'] = $entity['aadUserId'];
                    }

                    // set fields specific to Url entities
                    if ($entity['entityType'] == 'Url') {
                        $oc_log['url'] = urldecode($entity['url']);
                    }

                    // set fields specific to File entities
                    if ($entity['entityType'] == 'File') {
                        if (array_key_exists('sha1', $entity)) {
                            $oc_log['hash'] = $entity['sha1'];
                        }

                        $oc_log['vendorinfo'] = $entity['filePath'].'\\'.$entity['fileName'];
                        $oc_log['object'] = $entity['deviceId'];

                        if (array_key_exists('detectionStatus', $entity)) {
                            $oc_log['action'] = $entity['detectionStatus'];
                        }
                    }

                    // set fields specific to Process entities
                    if ($entity['entityType'] == 'Process') {
                        $oc_log['processid'] = $entity['processId'];

                        if (array_key_exists('processCommandLine', $entity)) {
                            $oc_log['command'] = $entity['processCommandLine'];
                        }
                        
                        if (array_key_exists('parentProcessId', $entity)) {
                            $oc_log['parentprocessid'] = $entity['parentProcessId'];
                        }
                    }

                    // set fields specific to User entities
                    if ($entity['entityType'] == 'User') {
                        $oc_log['account'] = $entity['accountName'];
                        $oc_log['login'] = $entity['accountName'];
                        
                        if (array_key_exists('domainName', $entity)) {
                            $oc_log['domainimpacted'] = $entity['domainName'];
                            $oc_log['domainorigin'] = $entity['domainName'];
                        }

                        $oc_log['objectname'] = $entity['aadUserId'];
                    }

                    // set fields specific to IP entities
                    if ($entity['entityType'] == 'Ip') {
                        $oc_log['sip'] = $entity['ipAddress'];
                        $oc_log['dip'] = $entity['ipAddress'];

                        if (array_key_exists('url', $entity)) {
                            $oc_log['url'] = urldecode($entity['url']);
                        }
                    }

                    // set fields specific to Registry entities
                    if ($entity['entityType'] == 'Registry') {
                        $oc_log['process'] = $entity['registryHive'];
                        $oc_log['vendorinfo'] = $entity['registryKey'];
                        $oc_log['version'] = $entity['registryValueType'];
                        $oc_log['subject'] = $entity['registryValue'];

                        if (array_key_exists('deviceId', $entity)) {
                            $oc_log['object'] = $entity['deviceId'];
                        }
                    }

                    file_put_contents(storage_path('app/output/defender/entities/'.$output_date.'-entities.json'), \Metaclassing\Utility::encodeJson($oc_log).PHP_EOL, FILE_APPEND);

                    // post entity log to the OC webhookbeat and dump response to file
                    $webhook_response = $crawler->post($webhook_uri, '', \Metaclassing\Utility::encodeJson($oc_log));
                    file_put_contents(storage_path('app/responses/deffore_webhook.response'), $webhook_response);

                    // be easy on the OC
                    sleep(1);


                    // clear out key/value pairs specific to entity logs
                    $oc_log['size'] = null;
                    $oc_log['account'] = null;
                    $oc_log['login'] = null;
                    $oc_log['sender'] = null;
                    $oc_log['recipient'] = null;
                    $oc_log['subject'] = null;
                    $oc_log['action'] = null;
                    $oc_log['objectname'] = null;
                    $oc_log['url'] = null;
                    $oc_log['hash'] = null;
                    $oc_log['vendorinfo'] = null;
                    $oc_log['object'] = null;
                    $oc_log['process'] = null;
                    $oc_log['processid'] = null;
                    $oc_log['command'] = null;
                    $oc_log['parentprocessid'] = null;
                    $oc_log['domainimpacted'] = null;
                    $oc_log['domainorigin'] = null;
                    $oc_log['sip'] = null;
                    $oc_log['dip'] = null;
                    $oc_log['version'] = null;
                    $oc_log['sessiontype'] = null;
                    $oc_log['result'] = null;
                    $oc_log['status'] = null;

                } // end of alert entities loop

                // reset the quantity field to the count of alerts in the incident for the next alert loop
                $oc_log['quantity']  = count($alerts);

            } // end of incident alerts loop

        } // end of incidents loop

    } // end of handle function


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
