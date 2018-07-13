<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetTriageSimulationReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:simulationreports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Triage reports for phishing simulations and post report back to GoPhish';

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
        Log::info(PHP_EOL.PHP_EOL.'***************************************'.PHP_EOL.'* Starting Triage Simulation Reports! *'.PHP_EOL.'***************************************');

        // read in rids from last run
        $rids_reported = \Metaclassing\Utility::decodeJson(file_get_contents(storage_path('app/collections/gophish_rids_reported.json')));
        Log::info('[+] count of rids already reported: '.count($rids_reported));

        // get triage auth data
        $triage_token = getenv('TRIAGE_TOKEN');
        $triage_email = getenv('TRIAGE_EMAIL');

        // setup crawler
        $cookiejar = storage_path('app/cookies/triage_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        //$start_date = Carbon::now()->subHour()->toATomString();
        $start_date = Carbon::now()->subMinutes(5)->toATomString();
        $end_date = Carbon::now()->toAtomString();

        // setup triage url
        $reports_url = getenv('TRIAGE_URL').'/reports?category_id=5&tags=Simulation&start_date='.$start_date.'&end_date='.$end_date;

        // create authorization header and set to crawler
        $headers = [
            'Authorization: Token token='.$triage_email.':'.$triage_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // include the response headers
        curl_setopt($crawler->curl, CURLOPT_HEADER, true);

        $simulation_array = [];
        $running_count = 0;
        do {
            Log::info('[+] reports url: '.$reports_url);

            // execute request
            $str_response = $crawler->get($reports_url);

            // dump response to file
            file_put_contents(storage_path('app/responses/triage_sim_reports.response'), $str_response);

            // get curl info
            $curl_info = $crawler->curl_getinfo();

            // get response header size from curl info
            $response_header_size = $curl_info['header_size'];

            // use response header size to parse out headers and data from response
            $response_headers = substr($str_response, 0, $response_header_size);
            $data = \Metaclassing\Utility::decodeJson(substr($str_response, $response_header_size));

            // add reports data to reports array
            $simulation_array[] = $data;
            $running_count += count($data);
            Log::info('[+] running count: '.$running_count);

            // dump headers and data to file
            file_put_contents(storage_path('app/responses/triage_sim_headers.response'), $response_headers);
            file_put_contents(storage_path('app/responses/triage_sim_data.response'), \Metaclassing\Utility::encodeJson($data));

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

                foreach ($links as $link) {
                    // explode the link string on semi-colon
                    $link_pieces = explode(';', trim($link));

                    // if we have the next URL then remove '<' and '>' and assign to reports url
                    if ($link_pieces[1] == ' rel="next"') {
                        $reports_url = preg_replace('/[<>]/', '', $link_pieces[0]);
                    }
                }
            }
        } while ($running_count < $total);

        // collapse simulation array down to simple array
        $simulation_collection = array_collapse($simulation_array);
        Log::info('[+] simulation collection count: '.count($simulation_collection));

        file_put_contents(storage_path('app/collections/triage_sim_reports.json'), \Metaclassing\Utility::encodeJson($simulation_collection));

        $rids = [];
        $rid_regex = '/^rid=(\w+)$/';

        foreach ($simulation_collection as $report) {
            Log::info('[+] searching for GoPhish url...');
            $gophish_url = null;

            // attempt to find GoPhish url in email_urls array
            foreach ($report['email_urls'] as $url) {
                // looks for a string that contains 'gophish' and does not contain 'track'
                if (strpos($url['url'], 'gophish') !== false && strpos($url['url'], 'track') === false) {
                    $gophish_url = $url['url'];
                    break;
                }
            }

            // check that we found the GoPhish url
            if ($gophish_url) {
                Log::info('[+] found GoPhish url: '.$gophish_url);

                // explode and mend back together to create GoPhish report url
                $gophish_url_pieces = explode('?', $gophish_url);
                $gophish_report_url = $gophish_url_pieces[0].'/report?'.$gophish_url_pieces[1];
                Log::info('[+] GoPhish report url: '.$gophish_report_url);

                // attempt to parse out user rid
                if (preg_match($rid_regex, $gophish_url_pieces[1], $hits)) {
                    // push user rid onto rids array
                    $rid = $hits[1];
                    array_push($rids, $rid);

                    // check if we've seen this rid before
                    if (!in_array($rid, $rids_reported)) {
                        // if not then hit the GoPhish report endpoint to submit user's report
                        $cookiejar = storage_path('app/cookies/gophish_cookie.txt');
                        $crawler = new \Crawler\Crawler($cookiejar);

                        $response = $crawler->get($gophish_report_url);

                        file_put_contents(storage_path('app/responses/gophish_report.response'), $response);
                    } else {
                        // otherwise, we've capture this user's report already
                        Log::info('[+] user already reported [rid='.$rid.']');
                    }
                } else {
                    Log::error('[!] failed to match rid in GoPhish url');
                }
            } else {
                Log::error('[!] GoPhish url not found!');
            }
        }

        // check if we have rids to save
        if (count($rids)) {
            $rids = array_merge($rids_reported, $rids);

            // dump collected rids to file for next execution
            file_put_contents(storage_path('app/collections/gophish_rids_reported.json'), \Metaclassing\Utility::encodeJson($rids));
        }

        Log::info('[+] done reporting campaign reports to GoPhish!');
    }
}
