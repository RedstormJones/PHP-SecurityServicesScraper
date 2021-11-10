<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetPhishLabsIncidents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:phishlabsincidents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get incidents from the PhishLabs Case API';

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
        Log::info('[GetPhishLabsIncidents.php] Starting API Poll!');

        //$date = Carbon::now()->subHour();
        $date = Carbon::now()->subMinutes(10);
        $date_str = $date->toIso8601ZuluString();

        $output_date = Carbon::now()->toDateString();

        $webhook_uri = getenv('WEBHOOK_URI');

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/phishlabs_case_api.txt');

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
            'created_after' => $date_str,
            'direction'     => 'asc',
            'sort'          => 'created_at',
        ];
        $url_params_str = $this->postArrayToString($url_params);

        // send request, capture response and dump to file
        $json_response = $crawler->get(getenv('PHISHLABS_CASE_URL').'/v1/incidents/EIR?'.$url_params_str);
        file_put_contents(storage_path('app/responses/phishlabs-incidents.json'), $json_response);

        // attempt to JSON decode the response
        try {
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            Log::error('[GetPhishLabsIncidents.php] attempt to decode JSON response failed: '.$e->getMessage());
            die('[GetPhishLabsIncidents.php] attempt to decode JSON response failed: '.$e->getMessage());
        }

        // check for errors or unknown results in the response
        if (array_key_exists('error', $response)) {
            Log::error('[GetPhishLabsIncidents.php] error in response: '.$json_response);
            die('[GetPhishLabsIncidents.php] error in response: '.$json_response.PHP_EOL);
        } elseif (array_key_exists('incidents', $response)) {
            $incidents = $response['incidents'];

            $incidents_count = $response['metadata']['count'];
            Log::info('[GetPhishLabsIncidents.php] count of incidents from last 10 minutes: '.$incidents_count);
        } else {
            Log::error('[GetPhishLabsIncidents.php] unidentified response: '.$json_response);
            die('[GetPhishLabsIncidents.php] unidentified response: '.$json_response);
        }

        // dump incidents collection to file
        file_put_contents(storage_path('app/collections/phishlabs-incidents.json'), \Metaclassing\Utility::encodeJson($incidents));

        // cycle through indicents
        foreach ($incidents as $data) {
            // JSON encode incident and append to output file
            $data_json = \Metaclassing\Utility::encodeJson($data)."\n";
            file_put_contents(storage_path('app/output/phishlabs_incidents/'.$output_date.'-phishlabs-incidents.log'), $data_json, FILE_APPEND);

            $webhook_response = $crawler->post($webhook_uri, '', $data_json);
            file_put_contents(storage_path('app/responses/webhook.response'), $webhook_response);
        }

        Log::info('[GetPhishLabsIncidents.php] DONE!');
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