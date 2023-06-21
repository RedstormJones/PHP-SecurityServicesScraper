<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetProofPointMessagesDelivered extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:ppmessagesdelivered';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get messages delivered from the ProofPoint SIEM API';

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
        /********************************
         * [1] Get ProofPoint SIEM data *
         ********************************/

        Log::info('[GetProofPointMessagesDelivered.php] Starting ProofPoint SIEM API Poll for MESSAGES DELIVERED!');

        $webhook_uri = getenv('WEBHOOK_URI');

        $date = Carbon::now()->toDateString();

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie_messagesdelivered.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth header and assign to crawler
        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        // define target url
        //$url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=3600';
        $since_seconds = 600;
        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/messages/delivered?format=json&sinceSeconds='.$since_seconds;


        // try to JSON decode the response
        try {
            // send GET request to url and dump response to file
            $json_response = $crawler->get($url);
            file_put_contents(storage_path('app/responses/proofpoint_messages_delivered.response'), $json_response);

            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            $response = null;
            Log::error('[GetProofPointMessagesDelivered.php] '.$e->getMessage());
            die('[GetProofPointMessagesDelivered.php] '.$e->getMessage());
        }

        if ($response) {
            // get the delivered messages from the response
            $messages_delivered = $response['messagesDelivered'];

            // if we didn't get any data then pop smoke and bail
            if (count($messages_delivered) === 0) {
                Log::info('[GetProofPointMessagesDelivered.php] no new MESSAGES_DELIVERED from ProofPoint for last 10 minutes - terminating execution');
                die('[GetProofPointMessagesDelivered.php] no new MESSAGES_DELIVERED retrieved from ProofPoint for last 10 minutes - terminating execution...'.PHP_EOL);
            } else {
                Log::info('[GetProofPointMessagesDelivered.php] count of messages delivered (last 10 mins): '.count($messages_delivered));
            }

            // setup collection
            $messages = [];

            // normalize blocked messages
            if (count($messages_delivered)) {
                foreach ($messages_delivered as $msg) {

                    // build message parts array
                    $message_parts = [];

                    if (count($msg['messageParts'])) {
                        foreach ($msg['messageParts'] as $msg_part) {
                            $message_parts[] = [
                                'content_type'      => $msg_part['contentType'],
                                'md5'               => $msg_part['md5'],
                                'filename'          => $msg_part['filename'],
                                'o_content_type'    => $msg_part['oContentType'],
                                'sha256'            => $msg_part['sha256'],
                                'disposition'       => $msg_part['disposition'],
                                'sandbox_status'    => $msg_part['sandboxStatus'],
                            ];
                        }
                    }

                    // build threats info map array
                    $threats_info = [];

                    if (count($msg['threatsInfoMap'])) {
                        // forensic reports array
                        $forensic_reports = [];

                        foreach ($msg['threatsInfoMap'] as $threat_info) {
                            // use threat ID to create forensics url
                            $url = 'https://tap-api-v2.proofpoint.com/v2/forensics?threatId='.$threat_info['threatID'].'&format=json';

                            // get threat forensic reports
                            $json_response = $crawler->get($url);
                            file_put_contents(storage_path('app/responses/pp_threat.response'), $json_response);

                            // try to JSON decode the response
                            try {
                                $response = \Metaclassing\Utility::decodeJson($json_response);
                            } catch (\Exception $e) {
                                $response = null;
                                Log::error('[GetProofPointMessagesDelivered.php] '.$e->getMessage());
                            }

                            if (array_key_exists('reports', $response)){
                                if (count($response['reports'])){
                                    // build forensic reports array
                                    foreach ($response['reports'] as $report) {
                                        if (array_key_exists('forensics', $report)){
                                            $forensics = $report['forensics'];
                                        } else {
                                            $forensics = null;
                                        }

                                        // add forensic reports to forensic_reports array
                                        $forensic_reports[] = [
                                            'threat_id'     => $threat_info['threatID'],
                                            'report_id'     => $report['id'],
                                            'report_name'   => $report['name'],
                                            'report_scope'  => $report['scope'],
                                            //'report_type'   => $report['type'],
                                            'threat_status' => $report['threatStatus'],
                                            'forensics'     => $forensics,
                                        ];
                                    }
                                }
                            } else {
                                $forensic_reports = null;
                            }

                            // build threats_info array
                            $threats_info[] = [
                                'threat_type'       => $threat_info['threatType'],
                                'threat'            => $threat_info['threat'],
                                'campaign_id'       => $threat_info['campaignID'],
                                'threat_id'         => $threat_info['threatID'],
                                'threat_time'       => $threat_info['threatTime'],
                                'classification'    => $threat_info['classification'],
                                'threat_status'     => $threat_info['threatStatus'],
                                'threat_url'        => $threat_info['threatUrl'],
                            ];
                        }
                    }

                    $messages[] = [
                        'qid'                    => $msg['QID'],
                        'phish_score'            => $msg['phishScore'],
                        'message_id'             => $msg['messageID'],
                        'cluster'                => $msg['cluster'],
                        'sender'                 => $msg['sender'],
                        'message_size'           => $msg['messageSize'],
                        'message_guid'           => $msg['GUID'],
                        'xmailer'                => $msg['xmailer'],
                        'modules_run'            => $msg['modulesRun'],
                        'quarantine_rule'        => $msg['quarantineRule'],
                        'sender_ip'              => $msg['senderIP'],
                        'quarantine_folder'      => $msg['quarantineFolder'],
                        'message_parts'          => $message_parts,
                        'threats_info_map'       => $threats_info,
                        'threat_forensics'       => $forensic_reports,
                        'spam_score'             => $msg['spamScore'],
                        'reply_to_address'       => $msg['replyToAddress'],
                        'impostor_score'         => $msg['impostorScore'],
                        'completely_rewritten'   => $msg['completelyRewritten'],
                        'cluster'                => $msg['cluster'],
                        'from_address'           => $msg['fromAddress'],
                        'subject'                => $msg['subject'],
                        'cc_addresses'           => $msg['ccAddresses'],
                        'recipient'              => $msg['recipient'],
                        'policy_routes'          => $msg['policyRoutes'],
                        'malware_score'          => $msg['malwareScore'],
                        'header_reply_to'        => $msg['headerReplyTo'],
                        'message_time'           => $msg['messageTime'],
                        'to_addresses'           => $msg['toAddresses'],
                        'header_from'            => $msg['headerFrom'],
                        'phish_score'            => $msg['phishScore'],
                        'proofpoint_type'        => 'MESSAGE_DELIVERED',
                    ];
                }
            }

            // dump collection to file
            file_put_contents(storage_path('app/collections/proofpoint-messages-delivered.json'), \Metaclassing\Utility::encodeJson($messages));

            // cycle through collection 
            foreach ($messages as $data) {
                // JSON encode each log and append to the output file
                $data_json = \Metaclassing\Utility::encodeJson($data)."\n";
                file_put_contents(storage_path('app/output/proofpoint/messages/'.$date.'-proofpoint-messages-delivered.log'), $data_json, FILE_APPEND);

                $threat_info = $data['threats_info_map'];

                $lr_message = [
                    'beatname'                  => 'webhookbeat',
                    'device_type'               => 'PROOFPOINT',
                    'subject'                   => $data['subject'],
                    'sender'                    => $data['sender'],
                    'recipient'                 => $data['recipient'],
                    'sip'                       => $data['sender_ip'],
                    'result'                    => $data['proofpoint_type'],
                    'action'                    => $data['quarantine_folder'],
                    'severity'                  => $data['phish_score'],
                    'reason'                    => $threat_info[0]['classification'],
                    'url'                       => $threat_info[0]['threat'],
                    'status'                    => $threat_info[0]['threat_status'],
                    'policy'                    => $threat_info[0]['threat_type'],
                    'vendorinfo'                => $threat_info[0]['threat_url'],
                    'whsdp'                     => True,
                    'fullyqualifiedbeatname'    => 'webhookbeat-proofpoint-message-delivered',
                    'original_message'          => $data_json
                ];

                // JSON encode message
                $lr_message_json = \Metaclassing\Utility::encodeJson($lr_message);

                // post JSON log to webhookbeat on the LR OC
                $webhook_response = $crawler->post($webhook_uri, '', $lr_message_json);
                file_put_contents(storage_path('app/responses/webhook.response'), $webhook_response);

                $curl_info = curl_getinfo($crawler->curl);
                $json_size = strlen($lr_message_json);
                $request_size = $curl_info['request_size'] + $json_size;
                Log::info('[GetProofPointMessagesBlocked.php] request size: '.$request_size);
            }
        } else {
            // otherwise pop smoke and bail
            Log::info('[GetProofPointMessagesDelivered.php] no data returned from ProofPoint SIEM API...');
        }

        Log::info('[GetProofPointMessagesDelivered.php] ProofPoint MESSAGES DELIVERED command completed!');
    }
}
