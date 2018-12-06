<?php

namespace App\Console\Commands\Reindex;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReindexToMonthly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reindex:tomonthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'utility command for reindexing daily indices to a monthly index in Elastic';

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
        // setup response path
        $response_path = storage_path('app/responses/');

        // get indices from file
        $json_indices = file_get_contents(storage_path('app/collections/indices-to-monthly.json'));

        // use regex to split indice string into an array
        //$indices = preg_split('/$\R?^/m', $indice_str);
        $indices = \Metaclassing\Utility::decodeJson($json_indices);

        // setup cookie
        $cookiejar = storage_path('app/cookies/elastic_cookie.txt');

        foreach ($indices as $index) {
            // trim off newline
            $daily_index_pattern = trim($index);
            Log::info('[***] starting on daily index pattern: '.$daily_index_pattern);

            $monthly_index_pattern = substr($daily_index_pattern, 0, -2);
            Log::info('[+] reindexing to monthly index pattern: '.$monthly_index_pattern);

            /************************************
             * REINDEX DAILY INDICES TO MONTHLY *
             ************************************/

            // build the reindex url
            $url = getenv('ELASTIC_CLUSTER').'/_reindex?requests_per_second=-1&wait_for_completion=false&refresh';

            // instantiate a crawler and set headers
            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // reindex to monthly index
            $post = [
                'source'    => [
                    'index' => $daily_index_pattern,
                    'size'  => 10000,
                ],
                'dest'      => [
                    'index' => $monthly_index_pattern,
                ],
            ];

            // JSON encode post data
            $post_json = \Metaclassing\Utility::encodeJson($post);

            // post, capture response and dump to file
            $json_response = $crawler->post($url, '', $post_json);
            file_put_contents(storage_path('app/responses/reindex_to_monthly_response.json'), $json_response);

            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);

            // check for failures
            if (array_key_exists('failures', $response) and count($response['failures'])) {
                Log::error('[!] ERROR when attempting to reindex '.$daily_index_pattern);
                die('[!] ERROR when attempting to reindex '.$daily_index_pattern.PHP_EOL);
            }

            // wait for task to complete before proceeding
            $task_id = $response['task'];
            Log::info('[+] reindex to monthly task id: '.$task_id);

            // instantiate new crawler and set headers
            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // enter loop to check task status until its complete
            do {
                $url = getenv('ELASTIC_CLUSTER').'/_tasks/'.$task_id;

                $json_response = $crawler->get($url);
                file_put_contents(storage_path('app/responses/reindex_to_monthly_task_response.json'), $json_response);

                $response = \Metaclassing\Utility::decodeJson($json_response);

                $completed = $response['completed'];
                $task = $response['task'];
                $status = $task['status'];
                $description = $task['description'];
                $total = $status['total'];
                $created = $status['created'];

                if ($total > 0) {
                    $percent_complete = round(($created / $total)*100, 2);
                } else {
                    $percent_complete = 0;
                }

                // if not completed then sleep for 5 seconds
                if (!$completed) {
                    Log::info('[+] '.$description.' percent complete ...'.strval($percent_complete).'%');
                    sleep(30);
                }
            } while (!$completed);

            /************************
             * DELETE DAILY INDICES *
             ************************/

            Log::info('[+] deleting daily indices '.$daily_index_pattern);

            // delete original index
            $url = getenv('ELASTIC_CLUSTER').'/'.$daily_index_pattern;

            // send delete request, capture response and dump to file
            $json_response = $crawler->delete($url);
            file_put_contents(storage_path('app/responses/delete_daily_indices_response.json'), $json_response);

            // JSON deocde response
            $response = \Metaclassing\Utility::decodeJson($json_response);

            // check for failures
            if (array_key_exists('acknowledged', $response) and !$response['acknowledged']) {
                Log::error('[!] ERROR when attempting to delete: '.$daily_index_pattern);
                die('[!] ERROR when attempting to delete: '.$daily_index_pattern.PHP_EOL);
            }

            // snooze
            sleep(3);

            Log::info('[***] reindex to monthly completed:  '.$daily_index_pattern.' --> '.$monthly_index_pattern);
        }
    }
}
