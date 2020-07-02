<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetProofPointSIEM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:proofpointsiem';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get data from ProofPoint SIEM API';

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

        Log::info(PHP_EOL.PHP_EOL.'*************************************'.PHP_EOL.'* Starting ProofPoint SIEM command! *'.PHP_EOL.'*************************************');

        $date = Carbon::now()->toDateString();

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth header and assign to crawler
        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        // define target url
        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=3600';

        // send GET request to url and dump response to file
        $json_response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/proofpoint.response'), $json_response);

        // try to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
            $error = 'No errors detected';
        } catch (\Exception $e) {
            $response = null;
            $error = $e->getMessage();
        }

        // check if decoding the JSON response was successful
        if ($response) {
            // get data from response
            $messages_delivered = $response['messagesDelivered'];
            $messages_blocked = $response['messagesBlocked'];
            $clicks_permitted = $response['clicksPermitted'];
            $clicks_blocked = $response['clicksBlocked'];

            // if we didn't get any data then just die
            if (count($messages_delivered) === 0 and count($messages_blocked) === 0 and count($clicks_permitted) === 0 and count($clicks_blocked) === 0) {
                Log::info('[-] no new data retrieved from ProofPoint - terminating execution');
                die('[-] no new data retrieved from ProofPoint - terminating execution...'.PHP_EOL);
            }

            // final data collection array
            $siem_data = [];

            // normalize delivered messages
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
                                $error = 'No errors detected';
                            } catch (\Exception $e) {
                                $response = null;
                                $error = $e->getMessage();
                            }

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

                    $siem_data[] = [
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
                                $error = 'No errors detected';
                            } catch (\Exception $e) {
                                $response = null;
                                $error = $e->getMessage();
                            }

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

                    $siem_data[] = [
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

            // normalize permitted clicks
            if (count($clicks_permitted)) {
                foreach ($clicks_permitted as $click) {

                    // build message parts array
                    $message_parts = [];

                    if (array_key_exists('messageParts', $click)) {
                        foreach ($click['messageParts'] as $msg_part) {
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

                    if (array_key_exists('threatsInfoMap', $click)) {
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
                                $error = 'No errors detected';
                            } catch (\Exception $e) {
                                $response = null;
                                $error = $e->getMessage();
                            }

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

                    $siem_data[] = [
                        'campaign_id'       => $click['campaignId'],
                        'threat_id'         => $click['threatID'],
                        'url'               => $click['url'],
                        'click_ip'          => $click['clickIP'],
                        'message_guid'      => $click['GUID'],
                        'recipient'         => $click['recipient'],
                        'threat_status'     => $click['threatStatus'],
                        'message_id'        => $click['messageID'],
                        'threat_url'        => $click['threatURL'],
                        'click_time'        => $click['clickTime'],
                        'sender'            => $click['sender'],
                        'threat_time'       => $click['threatTime'],
                        'classification'    => $click['classification'],
                        'sender_ip'         => $click['senderIP'],
                        'user_agent'        => $click['userAgent'],
                        'message_parts'     => $message_parts,
                        'threats_info_map'  => $threats_info,
                        'threat_forensics'  => $forensic_reports,
                        'proofpoint_type'   => 'CLICK_PERMITTED',
                    ];
                }
            }

            // normalize blocked clicks
            if (count($clicks_blocked)) {
                foreach ($clicks_blocked as $click) {

                    // build message parts array
                    $message_parts = [];

                    if (array_key_exists('messageParts', $click)) {
                        foreach ($click['messageParts'] as $msg_part) {
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

                    if (array_key_exists('threatsInfoMap', $click)) {
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
                                $error = 'No errors detected';
                            } catch (\Exception $e) {
                                $response = null;
                                $error = $e->getMessage();
                            }

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

                    $siem_data[] = [
                        'campaign_id'       => $click['campaignId'],
                        'threat_id'         => $click['threatID'],
                        'url'               => $click['url'],
                        'click_ip'          => $click['clickIP'],
                        'message_guid'      => $click['GUID'],
                        'recipient'         => $click['recipient'],
                        'threat_status'     => $click['threatStatus'],
                        'message_id'        => $click['messageID'],
                        'threat_url'        => $click['threatURL'],
                        'click_time'        => $click['clickTime'],
                        'sender'            => $click['sender'],
                        'threat_time'       => $click['threatTime'],
                        'classification'    => $click['classification'],
                        'sender_ip'         => $click['senderIP'],
                        'user_agent'        => $click['userAgent'],
                        'message_parts'     => $message_parts,
                        'threats_info_map'  => $threats_info,
                        'threat_forensics'  => $forensic_reports,
                        'proofpoint_type'   => 'CLICK_BLOCKED',
                    ];
                }
            }

            file_put_contents(storage_path('app/collections/proofpoint_siem.json'), \Metaclassing\Utility::encodeJson($siem_data));

            // setup a Kafka producer
            $config = \Kafka\ProducerConfig::getInstance();
            $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
            $producer = new \Kafka\Producer();

            foreach ($siem_data as $data) {
                $data_json = \Metaclassing\Utility::encodeJson($data);
                file_put_contents(storage_path('app/output/'.$date.'-proofpoint-siem.log'), $data_json, FILE_APPEND);

                // send data to Kafka
                $result = $producer->send([
                    [
                        'topic' => 'proofpoint_siem',
                        'value' => \Metaclassing\Utility::encodeJson($data),
                    ],
                ]);

                // check for errors
                if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                    //Log::error('[!] Error sending ProofPoint SIEM API data to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
                    Log::error('[!] Error sending ProofPoint SIEM API data to Kafka: '.\Metaclassing\Utility::encodeJson($result));
                    Log::error('[!] Error sending ProofPoint SIEM API data to Kafka - size of attempted message: '. mb_strlen(serialize($data), '8bit'));
                } else {
                    //Log::info('[*] ProofPoint SIEM data successfully sent to Kafka');
                }
            }
        } else {
            // otherwise pop smoke and bail
            Log::info('[-] no data returned from ProofPoint SIEM API...');
        }

        Log::info('[*] ProofPoint SIEM command completed! [*]'.PHP_EOL);
    }
}
