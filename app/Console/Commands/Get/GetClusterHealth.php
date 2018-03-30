<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetClusterHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:clusterhealth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query production elasticsearch cluster for health data';

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
        $cookiejar = storage_path('app/cookies/elasticsearch_health.txt');

        $crawler = new \Crawler\Crawler($cookiejar);

        $cluster_health_url = getenv('ELASTIC_CLUSTER').'/_cluster/health';
        echo 'cluster health url: '.$cluster_health_url.PHP_EOL;

        $headers = [
            'Authorization: Basic ***REMOVED***'
        ];

        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // read in old cluster health data
        $old_json_response = file_get_contents(storage_path('app/responses/elastic_cluster_health.json'));
        $old_response = \Metaclassing\Utility::decodeJson($old_json_response);

        // get new cluster health data
        $json_response = $crawler->get($cluster_health_url);
        file_put_contents(storage_path('app/responses/elastic_cluster_health.json'), $json_response);

        $response = \Metaclassing\Utility::decodeJson($json_response);

        $old_status = $old_response['status'];
        $new_status = $response['status'];
        if ($new_status == $old_status) {
            $status_string = 'Status unchanged: '.$new_status;
        }
        else {
            $status_string = $old_status.'  --->  '.$new_status;            
        }

        $old_relocating_shards = $old_response['relocating_shards'];
        $new_relocating_shards = $response['relocating_shards'];
        $delta_relocating_shards = $new_relocating_shards - $old_relocating_shards;

        $old_initializing_shards = $old_response['initializing_shards'];
        $new_initializing_shards = $response['initializing_shards'];
        $delta_initializing_shards = $new_initializing_shards - $old_initializing_shards;

        $old_unassigned_shards = $old_response['unassigned_shards'];
        $new_unassigned_shards = $response['unassigned_shards'];
        $delta_unassigned_shards = $new_unassigned_shards - $old_unassigned_shards;

        $old_pending_tasks = $old_response['number_of_pending_tasks'];
        $new_pending_tasks = $response['number_of_pending_tasks'];
        $delta_pending_tasks = $new_pending_tasks - $old_pending_tasks;

        $old_max_task_queue_wait = $old_response['task_max_waiting_in_queue_millis'];
        $new_max_task_queue_wait = $response['task_max_waiting_in_queue_millis'];
        $delta_max_task_queue_wait = $new_max_task_queue_wait - $old_max_task_queue_wait;

        $old_active_shards_perc = $old_response['active_shards_percent_as_number'];
        $new_active_shards_perc = $response['active_shards_percent_as_number'];
        $delta_active_shards_perc = $new_active_shards_perc - $old_active_shards_perc;

        $health_status = [
            'cluster_name'                  => $response['cluster_name'],
            'cluster_status'                => $status_string,
            'number_of_nodes'               => $response['number_of_nodes'],
            'number_of_data_nodes'          => $response['number_of_data_nodes'],
            'active_primary_shards'         => $response['active_primary_shards'],
            'total_active_shards'           => $response['active_shards'],
            'relocating_shards'             => $new_relocating_shards,
            'relocating_shards_delta'       => $delta_relocating_shards,
            'initializing_shards'           => $new_initializing_shards,
            'initializing_shards_delta'     => $delta_initializing_shards,
            'unassigned_shards'             => $new_unassigned_shards,
            'unassigned_shards_delta'       => $delta_unassigned_shards,
            'number_of_pending_tasks'       => $new_pending_tasks,
            'number_of_pending_tasks_delta' => $delta_pending_tasks,
            'max_task_queue_wait'           => $new_max_task_queue_wait,
            'max_task_queue_wait_delta'     => $delta_max_task_queue_wait
        ];

        $table_array = [
            'cluster_status'        => $status_string,
            'relocating_shards'     => $health_status['relocating_shards'],
            'relocating_delta'      => $health_status['relocating_shards_delta'],
            'initializing_shards'   => $health_status['initializing_shards'],
            'initializing_delta'    => $health_status['initializing_shards_delta'],
            'unassigned_shards'     => $health_status['unassigned_shards'],
            'unassigned_delta'      => $health_status['unassigned_shards_delta'],
            'pending_tasks'         => $health_status['number_of_pending_tasks'],
            'pending_delta'         => $health_status['number_of_pending_tasks_delta'],
            'max_queue_wait'        => $health_status['max_task_queue_wait'],
            'max_queue_wait_delta'  => $health_status['max_task_queue_wait_delta']
        ];

        $headers = array_keys($table_array);
        $values = array_values($table_array);

        $table_values = [
            [
                $table_array['cluster_status'],
                $table_array['relocating_shards'],
                $table_array['relocating_delta'],
                $table_array['initializing_shards'],
                $table_array['initializing_delta'],
                $table_array['unassigned_shards'],
                $table_array['unassigned_delta'],
                $table_array['pending_tasks'],
                $table_array['pending_delta'],
                $table_array['max_queue_wait'],
                $table_array['max_queue_wait_delta']
            ]
        ];

        if ($table_array['relocating_delta'] == 0) {
            $this->line('relocating_shard_delta: '.$table_array['relocating_delta']);
        }
        else if ($table_array['relocating_delta'] > 0) {
            $this->line('relocating_shards_delta: <fg=red>'.$table_array['relocating_delta'].'</>');
        }
        else {
            $this->line('relocating_shards_delta: <fg=green>'.$table_array['relocating_delta'].'</>');
        }

        if ($table_array['initializing_delta'] == 0) {
            $this->line('initializing_shard_delta: '.$table_array['initializing_delta']);
        }
        else if ($table_array['initializing_delta'] > 0) {
            $this->line('initializing_shards_delta: <fg=green>'.$table_array['initializing_delta'].'</>');
        }
        else {
            $this->line('initializing_shards_delta: <fg=red>'.$table_array['initializing_delta'].'</>');
        }

        if ($table_array['unassigned_delta'] == 0) {
            $this->line('unassigned_shard_delta: '.$table_array['unassigned_delta']);
        }
        else if ($table_array['unassigned_delta'] > 0) {
            $this->line('unassigned_shards_delta: <fg=red>'.$table_array['unassigned_delta'].'</>');
        }
        else {
            $this->line('unassigned_shards_delta: <fg=green>'.$table_array['unassigned_delta'].'</>');
        }

        if ($table_array['pending_delta'] == 0) {
            $this->line('pending_tasks_delta: '.$table_array['pending_delta']);
        }
        else if ($table_array['pending_delta'] > 0) {
            $this->line('pending_tasks_delta: <fg=red>'.$table_array['pending_delta'].'</>');
        }
        else {
            $this->line('pending_tasks_delta: <fg=green>'.$table_array['pending_delta'].'</>');
        }

        if ($table_array['max_queue_wait_delta'] == 0) {
            $this->line('max_task_queue_wait_delta: '.$table_array['max_queue_wait_delta']);
        }
        else if ($table_array['max_queue_wait_delta'] > 0) {
            $this->line('max_task_queue_wait_delta: <fg=red>'.$table_array['max_queue_wait_delta'].'</>');
        }
        else {
            $this->line('max_task_queue_wait_delta: <fg=green>'.$table_array['max_queue_wait_delta'].'</>');
        }

        $this->table($headers, $table_values);
    }
}
