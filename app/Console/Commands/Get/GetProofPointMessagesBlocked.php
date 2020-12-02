<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetProofPointMessagesBlocked extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:ppmessagesblocked';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get messages blocked from the ProofPoint SIEM API';

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

        Log::info('[GetProofPointMessagesBlocked.php] Starting ProofPoint SIEM API Poll for MESSAGES BLOCKED!');

        $date = Carbon::now()->toDateString();

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie_messagesblocked.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth header and assign to crawler
        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        // define target url
        //$url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=3600';
        $since_seconds = 600;
        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/messages/blocked?format=json&sinceSeconds='.$since_seconds;

        // send GET request to url and dump response to file
        $json_response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/proofpoint_messages_blocked.response'), $json_response);

        // try to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            $response = null;
            Log::error('[GetProofPointMessagesBlocked.php] '.$e->getMessage());
        }

        if ($response) {
            // get the blocked messages from the response
            $messages_blocked = $response['messagesBlocked'];

            // if we didn't get any data then pop smoke and bail
            if (count($messages_blocked) === 0) {
                Log::info('[GetProofPointMessagesBlocked.php] no new MESSAGES_BLOCKED retrieved from ProofPoint for last 1 hour - terminating execution');
                die('[GetProofPointMessagesBlocked.php] no new MESSAGES_BLOCKED retrieved from ProofPoint for last 1 hour - terminating execution...'.PHP_EOL);
            } else {
                Log::info('[GetProofPointMessagesBlocked.php] count of messages blocked (last 10 mins): '.count($messages_blocked));
            }

            // setup collection
            $messages = [];

            // normalize blocked messages
            if (count($messages_blocked)) {
                foreach ($messages_blocked as $msg) {

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
                                Log::error('[GetProofPointMessagesBlocked.php] '.$e->getMessage());
                            }

                            if (count($response['reports'])){
                                // build forensic reports array
                                foreach ($response['reports'] as $report) {
                                    // add forensic reports to forensic_reports array
                                    $forensic_reports[] = [
                                        'threat_id'     => $threat_info['threatID'],
                                        'report_id'     => $report['id'],
                                        'report_name'   => $report['name'],
                                        'report_scope'  => $report['scope'],
                                        //'report_type'   => $report['type'],
                                        'threat_status' => $report['threatStatus'],
                                        'forensics'     => $report['forensics'],
                                    ];
                                }
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
                        'proofpoint_type'        => 'MESSAGE_BLOCKED',
                    ];
                }
            }

            // dump collection to file
            file_put_contents(storage_path('app/collections/proofpoint-messages-blocked.json'), \Metaclassing\Utility::encodeJson($messages));

            // cycle through collection 
            foreach ($messages as $data) {
                // JSON encode each log and append to the output file
                $data_json = \Metaclassing\Utility::encodeJson($data)."\n";
                file_put_contents(storage_path('app/output/proofpoint/messages/'.$date.'-proofpoint-messages-blocked.log'), $data_json, FILE_APPEND);
            }
        } else {
            // otherwise pop smoke and bail
            Log::info('[GetProofPointMessagesBlocked.php] no data returned from ProofPoint SIEM API...');
        }

        Log::info('[GetProofPointMessagesBlocked.php] ProofPoint MESSAGES BLOCKED command completed!');
    }
}
