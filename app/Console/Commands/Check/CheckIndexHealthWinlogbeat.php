<?php

namespace App\Console\Commands\Check;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckIndexHealthWinlogbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:winlogbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check index health for winlogbeat';

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
        Log::info(PHP_EOL.PHP_EOL.'*******************************************'.PHP_EOL.'* Starting winlogbeat Index Health Check! *'.PHP_EOL.'*******************************************');

        $date = Carbon::now()->toDateString();
        $threshold_timestamp = Carbon::now()->subMinutes(5);
        Log::info('[+] date string: '.$date);
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

        // get the url
        $index = 'winlogbeat-'.$date;
        $es_url = getenv('ELASTIC_CLUSTER').'/'.$index.'/_search';
        Log::info('[+] elastic url: '.$es_url);

        $search_data = '{"query": {"match_all": {}},"size": 1,"sort": [{"@timestamp": {"order": "desc"}}]}';

        $json_response = $crawler->post($es_url, '', $search_data);
        file_put_contents(storage_path('app/responses/indexcheck-winlogbeat.json'), $json_response);

        try {
            // attempt to JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);
        } catch (\Exception $e) {
            Log::error('[!] failed to decode JSON response: '.$e->getMessage());
            die('[!] failed to decode JSON response: '.$e->getMessage().PHP_EOL);
        }

        if (array_key_exists('hits', $response)) {
            $last_log = $response['hits']['hits'][0]['_source'];

            $last_log_timestamp_pieces = explode('.', $last_log['@timestamp']);
            $last_log_timestamp = $last_log_timestamp_pieces[0];

            Log::info('[+] last log timestamp: '.$last_log_timestamp);

            $last_log_timestamp = Carbon::createFromFormat('Y-m-d\TH:i:s', $last_log_timestamp);
            Log::info('[+] carbon last log timestamp: '.$last_log_timestamp);


            if ($last_log_timestamp->lessThanOrEqualTo($threshold_timestamp)) {
                // POP SMOKE!
                $this->logToSlack($index.' has fallen 5 or more minutes behind!');
            } else {
                // we're good
                Log::info('[+] '.$index.' within acceptable range');
            }
        }
        else {
            Log::error('[!] no hits found for search query..');
            die('[!] no hits found for search query..'.PHP_EOL);
        }

        Log::info('[***] winlogbeat index health check command completed! [***]'.PHP_EOL);
    }

    public function logToSlack($message) {
        $cookiejar = storage_path('app/cookies/slack-cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $webhook_url = getenv('SLACK_WEBHOOK_INDEX_HEALTH_CHECK');

        $post_data = [
            'channel'    => '#index-health-checks',
            'username'   => 'webhookbot',
            'icon_emoji' => ':alert:',
            'text'       => $message,
        ];

        $json_post_data = 'payload='.\Metaclassing\Utility::encodeJson($post_data);
        Log::info('[+] slack post data: '.$json_post_data);

        $response = $crawler->post($webhook_url, '', $json_post_data);
        file_put_contents(storage_path('app/responses/slack-index-health-check.response'), $response);
    }
}
