<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetTriageReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:triagereports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Triage reports';

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
        Log::info(PHP_EOL.PHP_EOL.'************************************'.PHP_EOL.'* Starting Triage Reports crawler! *'.PHP_EOL.'************************************');

        // get triage auth data
        $triage_token = getenv('TRIAGE_TOKEN');
        $triage_email = getenv('TRIAGE_EMAIL');

        // setup crawler
        $cookiejar = storage_path('app/cookies/triage_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        //$start_date = Carbon::now()->subHour()->toATomString();
        $start_date = Carbon::now()->subMinute()->toATomString();
        $end_date = Carbon::now()->toAtomString();

        // setup triage url
        $reports_url = getenv('TRIAGE_URL').'/reports?start_date='.$start_date.'&end_date='.$end_date;

        // create authorization header and set to crawler
        $headers = [
            'Authorization: Token token='.$triage_email.':'.$triage_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // include the response headers
        curl_setopt($crawler->curl, CURLOPT_HEADER, true);

        $reports_array = [];
        $running_count = 0;
        do {
            Log::info('[+] reports url: '.$reports_url);

            // execute request
            $str_response = $crawler->get($reports_url);

            // dump response to file
            file_put_contents(storage_path('app/responses/triage_reports.response'), $str_response);

            // get curl info
            $curl_info = $crawler->curl_getinfo();

            // get response header size from curl info
            $response_header_size = $curl_info['header_size'];

            // use response header size to parse out headers and data from response
            $response_headers = substr($str_response, 0, $response_header_size);
            $data = \Metaclassing\Utility::decodeJson(substr($str_response, $response_header_size));

            // add reports data to reports array
            $reports_array[] = $data;
            $running_count += count($data);
            Log::info('[+] running count: '.$running_count);

            // dump headers and data to file
            file_put_contents(storage_path('app/responses/triage_headers.response'), $response_headers);
            file_put_contents(storage_path('app/responses/triage_data.response'), \Metaclassing\Utility::encodeJson($data));

            // explode response headers by new line
            $response_headers_array = explode("\n", trim($response_headers));

            // shift off the first element (http status)
            array_shift($response_headers_array);

            // recreate response header array using the header keys for the array keys
            $rheader_array = [];
            foreach ($response_headers_array as $rheader) {
                $rheader_pieces = explode(':', $rheader);

                // deal with the Link header
                if ($rheader_pieces[0] == 'Link') {
                    array_shift($rheader_pieces);

                    $link_str = '';
                    foreach ($rheader_pieces as $piece) {
                        $link_str .= $piece.':';
                    }

                    $rheader_array['Link'] = substr($link_str, 0, -1);
                } else {
                    $rheader_array[$rheader_pieces[0]] = $rheader_pieces[1];
                }
            }

            // extract total from response headers
            $total = $rheader_array['Total'];

            if (array_key_exists('Link', $rheader_array)) {
                // pull out the link header value and explode on the comma
                $link_header = $rheader_array['Link'];
                $links = explode(',', trim($link_header));

                print_r($links);

                foreach ($links as $link) {
                    // explode the link string on semi-colon
                    $link_pieces = explode(';', trim($link));
                    print_r($link_pieces);

                    // if we have the next URL then remove '<' and '>' and assign to reports url
                    if ($link_pieces[1] == ' rel="next"') {
                        $reports_url = preg_replace('/[<>]/', '', $link_pieces[0]);
                    }
                }
            }
        } while ($running_count < $total);

        // collapse reports collection array down
        $reports_collection = array_collapse($reports_array);
        Log::info('[+] reports collection count: '.count($reports_collection));

        $reports = [];

        // data normalization
        foreach ($reports_collection as $report) {
            // downstream processing will throw errors if data already has an id and
            // tag key, so pull id and tag and add back as triage_id and triage_tags
            $triage_id = array_pull($report, 'id');
            $triage_tags = array_pull($report, 'tags');
            $report['triage_id'] = $triage_id;
            $report['triage_tags'] = $triage_tags;

            // flatten out the email_urls array
            $email_urls_array = [];
            $email_urls = array_pull($report, 'email_urls');
            foreach ($email_urls as $url) {
                // this will give us an array of urls (strings) rather than an array of objects
                $email_urls_array[] = $url['url'];
            }
            $report['email_urls'] = $email_urls_array;

            $reports[] = $report;
        }

        file_put_contents(storage_path('app/collections/triage_reports.json'), \Metaclassing\Utility::encodeJson($reports));

        /*
        foreach ($reports_collection as $report) {
            // if $report['triage_tags'] contains 'SIMULATION' or something like that, then
            // extract the gophish url and POST the report event to it somehow..
        }
        */

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        Log::info('[+] [TRIAGE_REPORTS] sending ['.count($reports).'] Triage reports to Kafka...');

        // cycle through Cylance devices
        foreach ($reports as $report) {
            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'triage-reports',
                    'value' => \Metaclassing\Utility::encodeJson($report),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] [TRIAGE_REPORTS] Error sending Triage reports to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            }
        }
    }
}
