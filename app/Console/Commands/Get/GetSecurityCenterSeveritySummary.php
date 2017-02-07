<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\SecurityCenter\SecurityCenterSeveritySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSecurityCenterSeveritySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:severitysummary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get severity summary data from Security Center';

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
         * [1] Get the Security Center severity summaries
         */

        Log::info(PHP_EOL.PHP_EOL.'*****************************************************'.PHP_EOL.'* Starting SecurityCenter severity summary crawler! *'.PHP_EOL.'*****************************************************');

        $collection = [];
        $response_path = storage_path('app/responses/');

        // setup cookie jar to store cookies
        $cookiejar = storage_path('app/cookies/securitycenter_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // build post assoc array using authentication info
        $post = [
            'username' => getenv('SECURITYCENTER_USERNAME'),
            'password' => getenv('SECURITYCENTER_PASSWORD'),
        ];

        // set url to the token resource
        $url = 'https:/'.'/knetscalp001:443/rest/token';

        // post authentication data, capture response and dump to file
        $response = $crawler->post($url, '', $post);

        // JSON decode response
        $resp = \Metaclassing\Utility::decodeJson($response);

        // extract token value from response and echo to console
        $token = $resp['response']['token'];

        // setup HTTP headers with token value
        $headers = [
            'X-SecurityCenter: '.$token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $url = 'https://knetscalp001:443/rest/analysis';

        $post = [
            'type'          => 'vuln',
            'sourceType'    => 'cumulative',
            'query'         => [
                'tool'  => 'sumseverity',
                'type'  => 'vuln',
            ],
        ];

        // send request for resource, capture response and dump to file
        $response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));

        // JSON decode response
        $resp = \Metaclassing\Utility::decodeJson($response);

        // extract vulnerability results and add to collection
        $collection = $resp['response']['results'];

        Log::info('collected '.count($collection).' severity summaries');

        file_put_contents(storage_path('app/collections/sc_severity_summary.json'), \Metaclassing\Utility::encodeJson($collection));

        /*
         * [2] Process Security Center severity summaries into database
         */

        Log::info(PHP_EOL.'********************************************************'.PHP_EOL.'* Starting SecurityCenter severity summary processing! *'.PHP_EOL.'********************************************************');

        foreach ($collection as $sev_sum) {
            $severity_id = $sev_sum['severity']['id'];
            $severity_name = $sev_sum['severity']['name'];
            $severity_desc = $sev_sum['severity']['description'];

            $exists = SecurityCenterSeveritySummary::where('severity_id', $severity_id)->value('id');

            // if the model already exists then update it
            if ($exists) {
                $summary_model = SecurityCenterSeveritySummary::findOrFail($exists);

                Log::info('updating severity summary for '.$severity_desc);

                $summary_model->update([
                    'severity_count'    => $sev_sum['count'],
                    'data'              => \Metaclassing\Utility::encodeJson($sev_sum),
                ]);

                $summary_model->save();

                // touch summary model to update the 'updated_at' timestamp in case nothing was changed
                $summary_model->touch();
            } else {
                // otherwise, create a new severity summary model
                Log::info('creating new severity summary for '.$severity_desc.' with id of '.$severity_id);

                $new_summary = new SecurityCenterSeveritySummary();

                $new_summary->severity_id = $severity_id;
                $new_summary->severity_name = $severity_name;
                $new_summary->severity_count = $sev_sum['count'];
                $new_summary->severity_desc = $severity_desc;
                $new_summary->data = \Metaclassing\Utility::encodeJson($sev_sum);

                $new_summary->save();
            }
        }

        Log::info('* Completed SecurityCenter IP summary vulnerabilities! *');
    }
}
