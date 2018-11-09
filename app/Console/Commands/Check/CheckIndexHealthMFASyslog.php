<?php

namespace App\Console\Commands\Check;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckIndexHealthMFASyslog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:mfasyslog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check index health for MFA syslog';

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
        Log::info(PHP_EOL.PHP_EOL.'*******************************************'.PHP_EOL.'* Starting MFA syslog Index Health Check! *'.PHP_EOL.'*******************************************');

        // setup date and threshold variables
        $date = Carbon::now()->toDateString();
        $date = substr($date, 0, -3);
        $threshold_timestamp = Carbon::now()->subMinutes(30);
        Log::info('[+] threshold timestamp: '.$threshold_timestamp);

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
        $index = 'mfa_syslog-'.$date;
        $es_url = getenv('ELASTIC_CLUSTER').'/'.$index.'/_search';
        Log::info('[+] elastic url: '.$es_url);

        // setup search query
        $search_data = '{"query": {"match_all": {}},"size": 1,"sort": [{"@timestamp": {"order": "desc"}}]}';

        // post search query, capture JSON response and dump to file
        $json_response = $crawler->post($es_url, '', $search_data);
        file_put_contents(storage_path('app/responses/indexcheck-mfa_syslog.json'), $json_response);

        try {
            // attempt to JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            // pop smoke and bail
            Log::error('[!] failed to decode JSON response: '.$e->getMessage());
            die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }

        // check that we got any hits
        if (array_key_exists('hits', $response)) {
            // get the last log data from the response
            $last_log = $response['hits']['hits'][0]['_source'];

            // get rid of millisecond values from @timestamp
            $last_log_timestamp_pieces = explode('.', $last_log['@timestamp']);
            $last_log_timestamp = $last_log_timestamp_pieces[0];
            Log::info('[+] last log timestamp: '.$last_log_timestamp);

            // use the last log timestamp string to create a Carbon datetime object for comparison
            $last_log_timestamp = Carbon::createFromFormat('Y-m-d\TH:i:s', $last_log_timestamp);
            Log::info('[+] carbon last log timestamp: '.$last_log_timestamp);

            // compare the last log's timestamp with the threshold timestamp
            if ($last_log_timestamp->lessThanOrEqualTo($threshold_timestamp)) {
                // POP SMOKE!
                $this->logToSlack($index.' has fallen 30 or more minutes behind!');
            } else {
                // we're good
                Log::info('[+] '.$index.' within acceptable range');
            }
        } else {
            // otherwise, pop smoke and bail
            Log::error('[!] no hits found for search query..');
            die('[!] no hits found for search query..'.PHP_EOL);
        }

        Log::info('[***] mfa_syslog index health check command completed! [***]'.PHP_EOL);
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
            'icon_emoji' => ':alert:',
            'text'       => $message,
        ];

        // JSON encode post data array and append to payload=
        $json_post_data = 'payload='.\Metaclassing\Utility::encodeJson($post_data);
        Log::info('[+] slack post data: '.$json_post_data);

        // post message to Slack webhook, capture the response and dump it to file
        $response = $crawler->post($webhook_url, '', $json_post_data);
        file_put_contents(storage_path('app/responses/slack-index-health-check.response'), $response);

        // check for an 'ok' response and log accordingly
        if ($response == 'ok') {
            Log::info('[+] post to slack channel succeeded!');
        } else {
            Log::error('[!] post to slack channel failed!');
            die('[!] post to slack channel failed!'.PHP_EOL);
        }
    }
}
