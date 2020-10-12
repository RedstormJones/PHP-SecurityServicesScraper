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

        // calculate time ranges for URL parameters todate and fromdate
        $sub_hours = 1;
        $from_date = Carbon::now()->subHours($sub_hours)->setTimezone('America/New_York');
        $to_date = Carbon::now()->setTimezone('America/New_York')->toDateTimeString();
        $from_date_short = substr($from_date->toDateTimeString(), 0, -3);
        $to_date_short = substr($to_date, 0, -3);
        Log::info('[GetPhishLabsNewDomains.php] from date short: '.$from_date_short);
        Log::info('[GetPhishLabsNewDomains.php] to date short: '.$to_date_short);

        // setup date string for output filename
        $output_date = Carbon::now()->toDateString();

        // setup cookie jar for crawler
        $cookiejar = storage_path('app/cookies/phishlabs_new_domains.txt');

        // setup crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // get custID
        $phishlabs_cust_id = getenv('PHISHLABS_CUST_ID');

        // setup URL parameters
        $url_params = [
            'custid'    => $phishlabs_cust_id,
            'fromdate'  => $from_date_short,
            'todate'    => $to_date_short,
            'grpcatid'  => 7,
        ];
        $url_params_str = $this->postArrayToString($url_params);

        // build the new domains URI and replace any spaces with %20
        $phishlabs_new_domains_uri = getenv('PHISHLABS_FEED_URL').$url_params_str;
        $phishlabs_new_domains_uri = str_replace(' ', '%20', $phishlabs_new_domains_uri);

        try {
            // send GET request, capture and dump response to file
            $json_response = $crawler->get($phishlabs_new_domains_uri);
            file_put_contents(storage_path('app/responses/phishlabs_new_domains.json'), $json_response);

            // JSON decode response and log response count
            $response = \Metaclassing\Utility::decodeJson($json_response);

        } catch (\Exception $e) {
            // pop smoke and bail
            Log::error('[GetPhishLabsNewDomain.php] ERROR: '.$e);
            die($e);
        }

        // setup collection array
        $new_domains_collection = [];

        // cycle through the new domains, JSON encode with newline and append to output file
        foreach ($response as $new_domain) {
            // get domain from log
            $target_domain = $new_domain['Domain'];

            // use the create date to build a Carbon datetime object and don't shift the time to CST
            $created_date = Carbon::createFromFormat('!Y-n-j\TG:i:s', $new_domain['Createdate'], 'America/New_York');
            //Log::info('[GetPhishLabsNewDomains.php] Createdate pulled from new domain log: '.$created_date);
            //$created_date = $created_date->setTimezone('America/Chicago');
            //Log::info('[GetPhishLabsNewDomains.php] Createdate after setting TZ to America/Chicago: '.$created_date);

            Log::info('[GetPhishLabsNewDomains.php] checking created date ('.$created_date.') GTE from date ('.$from_date.')');

            // if create date is GTE to from date then this incident is new so log it
            if ($created_date >= $from_date) {
                Log::info('[GetPhishLabsNewDomains.php] New domain found '.$target_domain);

                // add new domain to new domains collection
                $new_domains_collection[] = $new_domain;

                // JSON encode and append to file
                $new_domain_json = \Metaclassing\Utility::encodeJson($new_domain)."\n";
                file_put_contents(storage_path('app/output/phishlabs_new_domains/'.$output_date.'-phishlabs-new-domains.log'), $new_domain_json, FILE_APPEND);
            }
        }

        Log::info('[GetPhishLabsNewDomains.php] count of new domains found in last '.$sub_hours.' hour(s): '.count($new_domains_collection));
        Log::info('[GetPhishLabsNewDomains.php] DONE!');
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
