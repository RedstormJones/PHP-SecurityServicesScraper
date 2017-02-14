<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\SecurityCenter\SecurityCenterAssetVuln;
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
        /*
         * [1] Get asset vulnerabilities
         */

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

        // get them vulnerabilities, or the important ones at least
        $asset_collection = $this->getAssetSummary($crawler);

        // instantiate asset summary array
        $assetsummary = [];

        // cycle through asset collection and build simple array
        foreach ($asset_collection as $result) {
            foreach ($result as $asset) {
                $assetsummary[] = $asset;
            }
        }

        Log::info('collected '.count($assetsummary).' asset vulnerabilities');

        // JSON encode simple array and dump to file
        file_put_contents(storage_path('app/collections/sc_asset_summary.json'), \Metaclassing\Utility::encodeJson($assetsummary));

        /*
         * [2] Process asset vulnerabilities into database
         */

        Log::info(PHP_EOL.'**********************************************'.PHP_EOL.'* Starting asset vulnerabilities processing! *'.PHP_EOL.'**********************************************');

        foreach ($assetsummary as $asset_data) {
            $asset_name = $asset_data['asset']['name'];
            $asset_id = $asset_data['asset']['id'];

            $exists = SecurityCenterAssetVuln::where('asset_id', $asset_id)->value('id');

            if ($exists) {
                $asset_vuln = SecurityCenterAssetVuln::find($exists);

                $asset_vuln->update([
                    'asset_name'      => $asset_name,
                    'asset_score'     => $asset_data['score'],
                    'critical_vulns'  => $asset_data['severityCritical'],
                    'high_vulns'      => $asset_data['severityHigh'],
                    'medium_vulns'    => $asset_data['severityMedium'],
                    'total_vulns'     => $asset_data['total'],
                    'data'            => \Metaclassing\Utility::encodeJson($asset_data),
                ]);

                $asset_vuln->save();

                // touch asset vuln record to updated the 'updated_at' timestamp in case nothing was changed
                $asset_vuln->touch();

                Log::info('updated asset vulnerability record for: '.$asset_name);
            } else {
                Log::info('creating new asset vulnerability record for: '.$asset_name);

                $new_asset = new SecurityCenterAssetVuln();

                $new_asset->asset_name = $asset_name;
                $new_asset->asset_id = $asset_id;
                $new_asset->asset_score = $asset_data['score'];
                $new_asset->critical_vulns = $asset_data['severityCritical'];
                $new_asset->high_vulns = $asset_data['severityHigh'];
                $new_asset->medium_vulns = $asset_data['severityMedium'];
                $new_asset->total_vulns = $asset_data['total'];
                $new_asset->data = \Metaclassing\Utility::encodeJson($asset_data);

                $new_asset->save();
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
