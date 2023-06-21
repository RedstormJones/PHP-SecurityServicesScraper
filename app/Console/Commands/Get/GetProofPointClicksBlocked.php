<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetProofPointClicksBlocked extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:ppclicksblocked';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get clicks blocked from the ProofPoint SIEM API';

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

        Log::info('[GetProofPointClicksBlocked.php] Starting ProofPoint SIEM API Poll for CLICKS BLOCKED!');

        // setup webhook URI for later
        $webhook_uri = getenv('WEBHOOK_URI');

        $date = Carbon::now()->toDateString();

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie_clicksblocked.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth header and assign to crawler
        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        // define target url
        //$url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=3600';
        $since_seconds = 600;
        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/clicks/blocked?format=json&sinceSeconds='.$since_seconds;

        // send GET request to url and dump response to file
        $json_response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/proofpoint_clicks_blocked.response'), $json_response);

        // try to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            $response = null;
            Log::error('[GetProofPointClicksBlocked.php] '.$e->getMessage());
        }

        if ($response) {
            // get the blocked clicks from the response
            $clicks_blocked = $response['clicksBlocked'];

            // if we didn't get any data then pop smoke and bail
            if (count($clicks_blocked) === 0) {
                Log::info('[GetProofPointClicksBlocked.php] no new CLICKS_BLOCKED from ProofPoint for last 1 hour - terminating execution');
                die('[GetProofPointClicksBlocked.php] no new CLICKS_BLOCKED retrieved from ProofPoint for last 1 hour - terminating execution...'.PHP_EOL);
            } else {
                Log::info('[GetProofPointClicksBlocked.php] count of clicks blocked (last 10 mins): '.count($clicks_blocked));
            }

            // setup collection
            $clicks = [];

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

                    // forensic reports array
                    $forensic_reports = [];

                    if (array_key_exists('threatsInfoMap', $click)) {

                        foreach ($click['threatsInfoMap'] as $threat_info) {
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
                                Log::error('[GetProofPointClicksBlocked.php] '.$e->getMessage());
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

                    $clicks[] = [
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

            // dump collection to file
            file_put_contents(storage_path('app/collections/proofpoint-clicks-blocked.json'), \Metaclassing\Utility::encodeJson($clicks));

            // setup webhook cookie jar
            $cookiejar = storage_path('app/cookies/OCwebhook.cookie');

            // setup new crawler
            $crawler = new \Crawler\Crawler($cookiejar);

            // cycle through collection 
            foreach ($clicks as $data) {
                // JSON encode each log and append to the output file
                $data_json = \Metaclassing\Utility::encodeJson($data);
                file_put_contents(storage_path('app/output/proofpoint/clicks/'.$date.'-proofpoint-clicks-blocked.log'), $data_json."\n", FILE_APPEND);

                $lr_click = [
                    'beatname'                  => 'webhookbeat',
                    'device_type'               => 'PROOFPOINT',
                    'sender'                    => $data['sender'],
                    'recipient'                 => $data['recipient'],
                    'sip'                       => $data['sender_ip'],
                    'result'                    => $data['proofpoint_type'],
                    'reason'                    => $data['classification'],
                    'url'                       => $data['url'],
                    'status'                    => $data['threat_status'],
                    'vendorinfo'                => $data['threat_url'],
                    'useragent'                 => $data['user_agent'],
                    'whsdp'                     => True,
                    'fullyqualifiedbeatname'    => 'webhookbeat-proofpoint-click-blocked',
                    'original_message'          => $data_json
                ];

                // JSON encode click
                $lr_click_json = \Metaclassing\Utility::encodeJson($lr_click);

                // post JSON log to webhookbeat on the LR OC
                $webhook_response = $crawler->post($webhook_uri, '', $lr_click_json);
                file_put_contents(storage_path('app/responses/webhook.response'), $webhook_response);

                $curl_info = curl_getinfo($crawler->curl);
                $json_size = strlen($lr_click_json);
                $request_size = $curl_info['request_size'] + $json_size;
                Log::info('[GetProofPointMessagesBlocked.php] request size: '.$request_size);
            }
        } else {
            // otherwise pop smoke and bail
            Log::info('[GetProofPointClicksBlocked.php] no data returned from ProofPoint SIEM API...');
        }

        Log::info('[GetProofPointClicksBlocked.php] ProofPoint CLICKS BLOCKED command completed!');
    }
}
