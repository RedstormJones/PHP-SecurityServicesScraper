<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReindexElastic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reindex:elastic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'utility command for reindexing indices in Elastic';

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
        $response_path = storage_path('app/responses/');

        $indice_str = file_get_contents(storage_path('app/collections/indices.txt'));

        $indices = preg_split('/$\R?^/m', $indice_str);

        $cookiejar = storage_path('app/cookies/elastic_cookie.txt');

        // cycle through indices
        foreach ($indices as $index) {
            $index = trim($index);
            Log::info('*** starting on index: '.$index.' ***');

            $url = getenv('ELASTIC_CLUSTER').'/_reindex?wait_for_completion=false';

            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // reindex to temp index
            $post = [
                'source'    => [
                    'index' => $index,
                ],
                'dest'      => [
                    'index' => $index.'-temp',
                ],
            ];

            $post_json = \Metaclassing\Utility::encodeJson($post);
            Log::info('[+] reindexing '.$index.' to '.$index.'-temp');

            $json_response = $crawler->post($url, '', $post_json);
            file_put_contents(storage_path('app/responses/reindex_to_temp.response'), $json_response);

            $response = \Metaclassing\Utility::decodeJson($json_response);
            if (array_key_exists('failures', $response) and count($response['failures'])) {
                Log::error('[!] ERROR when attempting to reindex '.$index);
                die('[!] ERROR when attempting to reindex '.$index.PHP_EOL);
            }

            // wait for task to complete before proceeding
            $task_id = $response['task'];
            Log::info('[+] reindex to temp task id: '.$task_id);

            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            do {
                $url = getenv('ELASTIC_CLUSTER').'/_tasks/'.$task_id;

                $json_response = $crawler->get($url);
                file_put_contents(storage_path('app/responses/reindex_task.response'), $json_response);

                $response = \Metaclassing\Utility::decodeJson($json_response);
                $completed = $response['completed'];
            } while (!$completed);

            // delete original index
            $url = getenv('ELASTIC_CLUSTER').'/'.$index;

            Log::info('[+] deleting original index '.$index);

            $json_response = $crawler->delete($url);
            file_put_contents(storage_path('app/responses/delete_orig.response'), $json_response);

            $response = \Metaclassing\Utility::decodeJson($json_response);
            if (array_key_exists('acknowledged', $response) and !$response['acknowledged']) {
                Log::error('[!] ERROR when attempting to delete original index '.$index);
                die('[!] ERROR when attempting to delete original index '.$index.PHP_EOL);
            }

            sleep(3);

            // reindex temp index back to original index
            $url = getenv('ELASTIC_CLUSTER').'/_reindex?wait_for_completion=false';

            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            $post = [
                'source'    => [
                    'index' => $index.'-temp',
                ],
                'dest'      => [
                    'index' => $index,
                ],
            ];

            $post_json = \Metaclassing\Utility::encodeJson($post);
            Log::info('[+] reindexing '.$index.'-temp back to original index '.$index);

            $json_response = $crawler->post($url, '', $post_json);
            file_put_contents(storage_path('app/responses/reindex_back.response'), $json_response);

            $response = \Metaclassing\Utility::decodeJson($json_response);
            if (array_key_exists('failures', $response) and count($response['failures'])) {
                Log::error('[!] ERROR when attempting to reindex '.$index.' back to original');
                die('[!] ERROR when attempting to reindex '.$index.' back to original'.PHP_EOL);
            }

            // wait for task to complete before proceeding
            $task_id = $response['task'];
            Log::info('[+] reindex back to original task id: '.$task_id);

            $crawler = new \Crawler\Crawler($cookiejar);
            $headers = [
                'Authorization: Basic '.getenv('ELASTIC_AUTH'),
                'Content-Type: application/json',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            do {
                $url = getenv('ELASTIC_CLUSTER').'/_tasks/'.$task_id;

                $json_response = $crawler->get($url);
                file_put_contents(storage_path('app/responses/reindex_task.response'), $json_response);

                $response = \Metaclassing\Utility::decodeJson($json_response);
                $completed = $response['completed'];
            } while (!$completed);

            // delete temp index
            $url = getenv('ELASTIC_CLUSTER').'/'.$index.'-temp';

            Log::info('[+] deleting temp index '.$index.'-temp');

            $json_response = $crawler->delete($url);
            file_put_contents(storage_path('app/responses/delete_temp.response'), $json_response);

            $response = \Metaclassing\Utility::decodeJson($json_response);
            if (array_key_exists('acknowledged', $response) and !$response['acknowledged']) {
                Log::error('[!] ERROR when attempting to delete temp index '.$index.'-temp');
                die('[!] ERROR when attempting to delete temp index '.$index.'-temp'.PHP_EOL);
            }

            sleep(3);

            Log::info('*** reindex completed for: '.$index.' ***');
        }
    }
}
