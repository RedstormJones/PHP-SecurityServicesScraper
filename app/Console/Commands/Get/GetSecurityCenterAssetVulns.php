<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSecurityCenterAssetVulns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:securitycenterassetvulns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new SecurityCenter asset vulnerabilities';

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
        Log::info(PHP_EOL.PHP_EOL.'********************************************'.PHP_EOL.'* Starting asset vulnterabilities crawler! *'.PHP_EOL.'********************************************');

        $response_path = storage_path('app/responses/');

        // setup cookie jar to store cookies
        $cookiejar = storage_path('app/cookies/securitycenter_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // build post assoc array using authentication info
        $post = [
            'username' => getenv('SECURITYCENTER_USERNAME'),
            'password' => getenv('SECURITYCENTER_PASSWORD'),
        ];

        // set url to the token resource and post authentication data
        $url = getenv('SECURITYCENTER_URL').'/rest/token';

        // capture response and dump to file
        $response = $crawler->post($url, '', $post);
        file_put_contents($response_path.'SC_login_assetvulns.dump', $response);

        // JSON decode response
        $resp = \Metaclassing\Utility::decodeJson($response);

        // extract token value from response and echo to console
        $token = $resp['response']['token'];

        // setup HTTP headers with token value
        $headers = [
            'X-SecurityCenter: '.$token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // get them vulnerabilities and collapse results into simple array
        $assetsummary = array_collapse($this->getAssetSummary($crawler));

        Log::info('collected '.count($assetsummary).' asset vulnerabilities');

        $asset_vulns = [];

        // normalize data
        foreach ($assetsummary as $asset) {
            $asset_name = $asset['asset']['name'];
            $asset_type = $asset['asset']['type'];
            $asset_id = intval($asset['asset']['id']);
            $asset_status = $asset['asset']['status'];
            $asset_desc = $asset['asset']['description'];

            $asset_vulns[] = [
                'asset_name'        => $asset_name,
                'asset_type'        => $asset_type,
                'asset_id'          => $asset_id,
                'asset_status'      => $asset_status,
                'asset_description' => $asset_desc,
                'asset_score'       => intval($asset['score']),
                'total_vulns'       => intval($asset['total']),
                'info_vulns'        => intval($asset['severityInfo']),
                'low_vulns'         => intval($asset['severityLow']),
                'medium_vulns'      => intval($asset['severityMedium']),
                'high_vulns'        => intval($asset['severityHigh']),
                'critical_vulns'    => intval($asset['severityCritical']),
            ];
        }

        // JSON encode simple array and dump to file
        file_put_contents(storage_path('app/collections/sc_asset_summary.json'), \Metaclassing\Utility::encodeJson($asset_vulns));

        // setup Kafka producer config
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate Kafka producer
        $producer = new \Kafka\Producer();

        foreach ($asset_vulns as $vuln) {
            // add upsert datetime
            $vuln['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'securitycenter_asset_vulns',
                    'value' => \Metaclassing\Utility::encodeJson($vuln),
                ],
            ]);

            // check for and log erros
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$vuln['asset_name']);
            }
        }

        Log::info('* Completed SecurityCenter asset vulnerabilities! *');
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

    /**
     * Function to pull vulnerability data from Security Center 5.4.0 API.
     *
     * @return array
     */
    public function getAssetSummary($crawler)
    {
        // point url to the resource we want
        $url = getenv('SECURITYCENTER_URL').'/rest/analysis';

        // instantiate the collections array, set count to 0 and set endoffset to 1000 (since pagesize = 1000)
        $collection = [];
        $count = 0;
        $endoffset = 1000;
        $page = 1;

        do {
            // setup post array
            $post = [
                'page'          => $page,
                'page_size'     => 1000,
                'type'          => 'vuln',
                'sourceType'    => 'cumulative',
                'query'         => [
                    'tool'      => 'sumasset',
                    'type'      => 'vuln',
                    'filters'   => [
                        /*
                        [
                            'filterName' => 'exploitAvailable',
                            'operator'   => '=',
                            'value'      => 'true',
                        ],
                        */
                    ],
                    'startOffset'   => $count,
                    'endOffset'     => $endoffset,
                ],
            ];

            // send request for resource, capture response and dump to file
            $response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));
            file_put_contents(storage_path('app/responses/SC_assetvulns.dump'.$page), $response);

            // JSON decode response
            $resp = \Metaclassing\Utility::decodeJson($response);

            // extract vulnerability results and add to collection
            $collection[] = $resp['response']['results'];

            // set total to the value of totalRecords in the response
            $total = $resp['response']['totalRecords'];

            // add number of returned records to count
            $count += $resp['response']['returnedRecords'];

            // add number of returned records to new count value for next endoffset
            $endoffset = $count + $resp['response']['returnedRecords'];

            // increment page
            $page++;

            // wait a second before hammering the Security Center API again
            sleep(1);
        } while ($count < $total);

        return $collection;
    }
}
