<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetProofPointClicksPermitted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:ppclickspermitted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get clicks permitted from the ProofPoint SIEM API';

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

        Log::info('[GetProofPointClicksPermitted.php] Starting ProofPoint SIEM API Poll for CLICKS PERMITTED!');

        $date = Carbon::now()->toDateString();

        // setup cookie file and instantiate crawler
        $cookiejar = storage_path('app/cookies/proofpointcookie_clickspermitted.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup auth header and assign to crawler
        $header = [
            'Authorization: Basic '.getenv('PROOFPOINT_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $header);

        // define target url
        //$url = 'https://tap-api-v2.proofpoint.com/v2/siem/all?format=json&sinceSeconds=3600';
        $since_seconds = 600;
        $url = 'https://tap-api-v2.proofpoint.com/v2/siem/clicks/permitted?format=json&sinceSeconds='.$since_seconds;

        // send GET request to url and dump response to file
        $json_response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/proofpoint_clicks_permitted.response'), $json_response);

        // try to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            $response = null;
            Log::error('[GetProofPointClicksPermitted.php] '.$e->getMessage());
        }

        if ($response) {
            // get the permitted clicks from the response
            $clicks_permitted = $response['clicksPermitted'];

            // if we didn't get any data then pop smoke and bail
            if (count($clicks_permitted) === 0) {
                Log::info('[GetProofPointClicksPermitted.php] no new CLICKS_PERMITTED from ProofPoint for last 1 hour - terminating execution');
                die('[GetProofPointClicksPermitted.php] no new CLICKS_PERMITTED retrieved from ProofPoint for last 1 hour - terminating execution...'.PHP_EOL);
            } else {
                Log::info('[GetProofPointClicksPermitted.php] count of clicks permitted (last 10 mins): '.count($clicks_permitted));
            }

            // setup collection
            $clicks = [];

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
                                Log::error('[GetProofPointClicksPermitted.php] '.$e->getMessage());
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
                        'proofpoint_type'   => 'CLICK_PERMITTED',
                    ];
                }
            }

            // dump collection to file
            file_put_contents(storage_path('app/collections/proofpoint-clicks-permitted.json'), \Metaclassing\Utility::encodeJson($clicks));

            // cycle through collection 
            foreach ($clicks as $data) {
                // JSON encode each log and append to the output file
                $data_json = \Metaclassing\Utility::encodeJson($data)."\n";
                file_put_contents(storage_path('app/output/proofpoint/messages/clicks/'.$date.'-proofpoint-clicks-permitted.log'), $data_json, FILE_APPEND);
            }
        } else {
            // otherwise pop smoke and bail
            Log::info('[GetProofPointClicksPermitted.php] no data returned from ProofPoint SIEM API...');
        }

        Log::info('[GetProofPointClicksPermitted.php] ProofPoint CLICKS PERMITTED command completed!');
    }
}
