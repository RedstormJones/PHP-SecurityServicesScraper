<?php

namespace App\Console\Commands\Check;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CheckIndexHealthElastAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:elastalert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check index health for ElastAlert';

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
        // setup logging config for this command
        $today = Carbon::now()->toDateString();
        $log = new Logger('index_health_check_elastalert');
        $log->pushHandler(new StreamHandler(storage_path('logs/checks/'.$today.'_index_health_check_elastalert.log'), Logger::INFO));

        $log->info('Starting ElastAlert Index Health Check!');

        // setup date and threshold variables
        $date = Carbon::now()->toDateString();
        $threshold_timestamp = Carbon::now()->subMinutes(5);
        $log->info('threshold timestamp: '.$threshold_timestamp);

        // setup crawler
        $cookiejar = storage_path('app/cookies/elasticsearch_health.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup and apply auth header
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic '.getenv('ELASTIC_AUTH'),
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // build the elastic url
        $index = 'elastalert-productionhive_status*';
        $es_url = getenv('ELASTIC_7_CLUSTER').'/'.$index.'/_search';
        $log->info('elastic url: '.$es_url);

        // setup search query
        $search_data = '{"query": {"match_all": {}},"size": 1,"sort": [{"@timestamp": {"order": "desc"}}]}';

        // post search query, capture JSON response and dump to file
        $json_response = $crawler->post($es_url, '', $search_data);
        file_put_contents(storage_path('app/responses/indexcheck-elastalert-status.json'), $json_response);

        try {
            // attempt to JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            // pop smoke and bail
            $log->error('[ERROR] failed to decode JSON response: '.$e->getMessage());
            die('[ERROR] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }

        // check that we got any hits
        if (array_key_exists('hits', $response)) {
            // get the last log data from the response
            $last_log = $response['hits']['hits'][0]['_source'];

            // get rid of millisecond values from @timestamp
            $last_log_timestamp_pieces = explode('.', $last_log['@timestamp']);
            $last_log_timestamp = $last_log_timestamp_pieces[0];
            $log->info('last log timestamp: '.$last_log_timestamp);

            // use the last log timestamp string to create a Carbon datetime object for comparison
            $last_log_timestamp = Carbon::createFromFormat('Y-m-d\TH:i:s', $last_log_timestamp);
            $log->info('carbon last log timestamp: '.$last_log_timestamp);

            // compare the last log's timestamp with the threshold timestamp
            if ($last_log_timestamp->lessThanOrEqualTo($threshold_timestamp)) {
                // POP SMOKE!
                //$this->logToSlack($index.' has fallen 5 or more minutes behind!');
                $this->logToMSTeams($index.' has fallen 5 or more minutes behind!');
            } else {
                // we're good
                $log->info(''.$index.' within acceptable range');
            }
        } elseif (array_key_exists('error', $response)) {
            // otherwise, check if we got an error
            $error = $response['error'];

            // build error string
            $error_string = $error['type'].' - '.$error['index'].PHP_EOL.'reason: '.$error['reason'];

            // pop smoke and bail
            $log->error('[ERROR] '.$error_string);
            //$this->logToSlack($error_string);
            $this->logToMSTeams($error_string);
            die($error_string);
        } else {
            // otherwise, pop smoke and bail
            $log->error('[ERROR] no hits found for search query..');
            die('[ERROR] no hits found for search query..'.PHP_EOL);
        }

        $log->info('ElastAlert index health check command completed!');
    }

    /**
     * Function to send alert and log messages to a particular Slack channel.
     *
     * @return null
     */
    public function logToSlack($message)
    {
        // setup crawler
        $cookiejar = storage_path('app/cookies/slack-cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup Slack webhook url
        $webhook_url = getenv('SLACK_WEBHOOK_INDEX_HEALTH_CHECK');

        // build post data array
        $post_data = [
            'channel'    => '#index-health-checks',
            'username'   => 'webhookbot',
            'icon_emoji' => ':warning:',
            'text'       => $message,
        ];

        // JSON encode post data array and append to payload=
        $json_post_data = 'payload='.\Metaclassing\Utility::encodeJson($post_data);
        $log->info('[+] slack post data: '.$json_post_data);

        // post message to Slack webhook, capture the response and dump it to file
        $response = $crawler->post($webhook_url, '', $json_post_data);
        file_put_contents(storage_path('app/responses/slack-index-health-check.response'), $response);

        // check for an 'ok' response and log accordingly
        if ($response == 'ok') {
            $log->info('[+] post to slack channel succeeded!');
        } else {
            $log->error('[ERROR] post to slack channel failed!');
            die('[ERROR] post to slack channel failed!'.PHP_EOL);
        }
    }

    /**
     * Function to send alert and log messages to a particular MS Teams channel.
     *
     * @return null
     */
    public function logToMSTeams($message)
    {
        // setup crawler
        $cookiejar = storage_path('app/cookies/ms-teams-cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // setup MS Teams webhook url
        $webhook_url = getenv('MS_TEAMS_INDEX_HEALTH_CHECK_WEBHOOK');

        // build post data array
        $post_data = [
            'text'  => $message,
        ];

        // JSON encode post data array
        $json_post_data = \Metaclassing\Utility::encodeJson($post_data);
        $log->info('MS Teams post data: '.$json_post_data);

        // post message to MS Teams webhook, capture response and dump it to file
        $response = $crawler->post($webhook_url, $webhook_url, $json_post_data);
        file_put_contents(storage_path('app/responses/ms-teams-index-health-check.response'), $response);

        // check response for errors
        if ($response == 1) {
            $log->info('post to Teams channel successful!');
        } else {
            $log->error('[ERROR] post to Teams channel failed!');
            die('[ERROR] post to Teams channel failed!'.PHP_EOL);
        }
    }
}
