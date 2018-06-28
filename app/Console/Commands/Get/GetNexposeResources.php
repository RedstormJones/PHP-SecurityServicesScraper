<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetNexposeResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:nexposeresources';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the list of resources available in Nexpose';

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
        Log::info(PHP_EOL.PHP_EOL.'***************************************'.PHP_EOL.'* Starting Nexpose Resources crawler! *'.PHP_EOL.'***************************************');

        // get creds and build auth string
        $username = getenv('NEXPOSE_USERNAME');
        $password = getenv('NEXPOSE_PASSWORD');

        $auth_str = base64_encode($username.':'.$password);

        // response path
        $response_path = storage_path('app/responses/');

        // cookie jar
        $cookiejar = storage_path('app/cookies/nexpose_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // set url
        $url = getenv('NEXPOSE_URL').'/assets';
        Log::info('[+] nexpose url: '.$url);

        // auth header
        $headers = [
            'Authorization: Basic '.$auth_str,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $asset_resources = [];

        do {
            // send request and capture response
            $json_response = $crawler->get($url);

            // dump response to file
            file_put_contents($response_path.'nexpose_assets.response', $json_response);
            $response = \Metaclassing\Utility::decodeJson($json_response);

            //$page_num = $response['page']['number'];
            //$total_pages = $response['page']['totalPages'];

            $links = $response['links'];

            $asset_resources[] = $response['resources'];

            foreach ($links as $link) {
                if ($link['rel'] == 'next') {
                    $url = $link['href'];
                    break;
                } else {
                    $url = null;
                }
            }
        } while ($url);
    }
}
