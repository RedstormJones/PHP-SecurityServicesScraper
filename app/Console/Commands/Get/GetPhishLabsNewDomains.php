<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetPhishLabsNewDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:newdomains';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new look-alike domains from PhishLabs';

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
        Log::info('[GetPhishLabsNewDomains.php] Starting PhishLabs New Domains API Poll!');

        // calculate time ranges for URL parameters
        $sub_hours = 5;
        $output_date = Carbon::now()->toDateString();
        $from_date = substr(Carbon::now()->subHours($sub_hours)->toDateTimeString(), 0, -3);
        $to_date = substr(Carbon::now()->toDateTimeString(), 0, -3);

        // setup cookie jar for crawler
        $cookiejar = storage_path('app/cookies/phishlabs_new_domains.txt');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // get custID
        $phishlabs_cust_id = getenv('PHISHLABS_CUST_ID');

        // setup URL parameters
        $url_params = [
            'custid'    => $phishlabs_cust_id,
            'fromdate'  => $from_date,
            'todate'    => $to_date,
            'grpcatid'  => 7,
        ];
        $url_params_str = $this->postArrayToString($url_params);

        $phishlabs_new_domains_uri = getenv('PHISHLABS_FEED_URL').$url_params_str;
        $phishlabs_new_domains_uri = str_replace(' ', '%20', $phishlabs_new_domains_uri);
        Log::info('[GetPhishLabsNewDomains.php] PhishLabs new domains URI: '.$phishlabs_new_domains_uri);

        try {
            // send GET request, capture and dump response to file
            $json_response = $crawler->get($phishlabs_new_domains_uri);
            file_put_contents(storage_path('app/responses/phishlabs_new_domains.json'), $json_response);

            // JSON decode response and log response count
            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info('[GetPhishLabsNewDomains.php] count of new domains found in last '.$sub_hours.' hour(s): '.count($response));

        } catch (\Exception $e) {
            // pop smoke and bail
            Log::error('[GetPhishLabsNewDomain.php] ERROR: '.$e);
            die($e);
        }

        // cycle through the new domains, JSON encode with newline and append to output file
        foreach ($response as $new_domain) {
            $new_domain_json = \Metaclassing\Utility::encodeJson($new_domain)."\n";
            file_put_contents(storage_path('app/output/phishlabs_new_domains/'.$output_date.'-phishlabs-new-domains.log'), $new_domain_json, FILE_APPEND);
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
