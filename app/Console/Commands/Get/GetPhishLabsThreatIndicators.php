<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetPhishLabsThreatIndicators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:threatindicators';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get threat indicators from PhishLabs API';

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
        Log::info('[GetPhishLabsThreatIndicators.php] Starting API Poll!');

        $date = Carbon::now()->toDateString();

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/phishlabs.txt');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // get auth string from secrets file
        $phishlabs_auth = getenv('PHISHLABS_AUTH');

        // build auth header and add to crawler
        $headers = [
            'Authorization: Basic '.$phishlabs_auth,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // setup url params and convert to &-delimited string
        $url_params = [
            //'since' => '1h',
            'since' => '10m',
            'limit' => 10000,
            'sort'  => 'createdAt',
        ];
        $url_params_str = $this->postArrayToString($url_params);

        // send request, capture response and dump to file
        $json_response = $crawler->get(getenv('PHISHLABS_URL').$url_params_str);
        file_put_contents(storage_path('app/responses/phishlabs-threats.json'), $json_response);

        // attempt to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            Log::error('[GetPhishLabsThreatIndicators.php] attempt to decode JSON response failed: '.$e->getMessage());
            die('[GetPhishLabsThreatIndicators.php] attempt to decode JSON response failed: '.$e->getMessage());
        }

        // check for errors or unknown results in the response
        if (array_key_exists('error', $response)) {
            Log::error('[GetPhishLabsThreatIndicators.php] error in response: '.$json_response);
            die('[GetPhishLabsThreatIndicators.php] error in response: '.$json_response.PHP_EOL);
        } elseif (array_key_exists('data', $response)) {
            $data = $response['data'];

            $indicators_count = $response['meta']['count'];
            Log::info('[GetPhishLabsThreatIndicators.php] count of IOCs from last 10 minutes: '.$indicators_count);
        } else {
            Log::error('[GetPhishLabsThreatIndicators.php] unidentified response: '.$json_response);
            die('[GetPhishLabsThreatIndicators.php] unidentified response: '.$json_response);
        }

        // setup indicators collection
        $indicators_collection = [];

        // cycle through "incidents"
        foreach ($data as $incident) {
            // create incident array for these indicators
            $incident_info = [
                'id'            => $incident['id'],
                'created_at'    => $incident['createdAt'],
                'description'   => $incident['shortDescription'],
                'reference_id'  => $incident['referenceId'],
            ];

            // cycle through incident indicators
            foreach ($incident['indicators'] as $indicator) {

                $updated_at = NULL;
                if (array_key_exists('updatedAt', $indicator)) {
                    $updated_at = $indicator['updatedAt'];
                }

                // check for indicator attributes
                if (array_key_exists('attributes', $indicator)) {
                    $attributes = [];

                    // build attributes array using attribute name for key
                    foreach ($indicator['attributes'] as $attribute) {
                        $attributes[$attribute['name']] = [
                            'id'            => $attribute['id'],
                            'created_at'    => $attribute['createdAt'],
                            'value'         => $attribute['value'],
                        ];
                    }

                    // add indicator with attributes to collection
                    $indicators_collection[] = [
                        'incident'              => $incident_info,
                        'indicator_id'          => $indicator['id'],
                        'created_at'            => $indicator['createdAt'],
                        'value'                 => $indicator['value'],
                        'indicator_type'        => $indicator['type'],
                        'false_positive'        => $indicator['falsePositive'],
                        'updated_at'            => $updated_at,
                        'attributes'            => $attributes,
                    ];
                } else {
                    // add indicator w/o attributes to collection
                    $indicators_collection[] = [
                        'incident'              => $incident_info,
                        'indicator_id'          => $indicator['id'],
                        'created_at'            => $indicator['createdAt'],
                        'value'                 => $indicator['value'],
                        'indicator_type'        => $indicator['type'],
                        'false_positive'        => $indicator['falsePositive'],
                        'updated_at'            => $updated_at,
                    ];
                }
            }
        }

        /*
        $crawler = new \Crawler\Crawler($cookiejar);
        $headers = [
            'Content-Type: application/json'
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $proofpoint_url_decode = 'https://tap-api-v2.proofpoint.com/v2/url/decode';

        foreach ($indicators_collection as $indicator) {
            if ($indicator['indicator_type'] == 'URL') {

                $post_body = [
                    'urls'  => [
                        $indicator['value']
                    ]
                ];
                $post_body_json = \Metaclassing\Utility::encodeJson($post_body);

                $response_json = $crawler->post($proofpoint_url_decode, '', $post_body_json);
                $response = \Metaclassing\Utility::decodeJson($response_json);

                foreach ($response['urls'] as $r) {
                    if ($r['success']) {
                        $indicator['value'] = $r['encodedUrl'];
                    }
                }
            }
        }
        */

        // dump indicators collection to file
        file_put_contents(storage_path('app/collections/phishlabs-threat-indicators.json'), \Metaclassing\Utility::encodeJson($indicators_collection));

        // cycle through threat indicators
        foreach ($indicators_collection as $data) {
            // JSON encode each IOC and append to output file
            $data_json = \Metaclassing\Utility::encodeJson($data)."\n";
            file_put_contents(storage_path('app/output/phishlabs/'.$date.'-phishlabs-threat-indicators.log'), $data_json, FILE_APPEND);
        }

        Log::info('[GetPhishLabsThreatIndicators.php] DONE!');
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
