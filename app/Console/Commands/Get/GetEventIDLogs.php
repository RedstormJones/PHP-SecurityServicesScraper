<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetEventIDLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:eventidlogs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get specified event ID logs from ElasticSearch winlogbeat* index';

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
        $cookiejar = storage_path('app/cookies/elastic_cookie.txt');

        $crawler = new \Crawler\Crawler($cookiejar);

        //$auth_url = 'http://10.243.36.53:9200/_xpack/security/_authenticate';
        $winlogbeat_url = 'http://10.243.36.53:9200/winlogbeat_2017-11-20/_search';

        $headers = [
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $event_ids = [
            4768,
            4769,
            4770,
            4624,
            6272,
            6273,
            4720,
            4722,
            4723,
            4724,
            4725,
            4726,
            4728,
            4732,
            4740,
            4756,
            4767,
            4776,
            4625,
            4771
        ];

        foreach ($event_ids as $event_id)
        {
            $search_query = [
                'query' => [
                    'bool'  => [
                        'must'  => [
                            [
                                'match' => [
                                    'event_id'  => $event_id
                                ]
                            ],
                            [
                                'range' => [
                                    '@timestamp'    => [
                                        'gte'   => '2017-11-01T00:00:00Z',
                                        'lte'   => 'now'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $json_response = $crawler->post($winlogbeat_url, '', \Metaclassing\Utility::encodeJson($search_query));
            $response = \Metaclassing\Utility::decodeJson($json_response);

            $hits = $response['hits']['hits'];

            $event_data = [];

            foreach ($hits as $hit)
            {
                $event_data[] = $hit['_source'];
            }

            if ($event_id == 4726 OR $event_id == 4732 OR $event_id == 6272 OR $event_id == 6273)
            {
                echo PHP_EOL.PHP_EOL.'EVENT ID: '.$event_id.PHP_EOL;
                print_r($event_data);
            }
            //file_put_contents(storage_path('app/responses/winlogbeat/windowslogs_eventid_'.$event_id.'.json'), \Metaclassing\Utility::encodeJson($event_data));
        }

    }
}
