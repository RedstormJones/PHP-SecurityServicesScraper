<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class GetZScalerUrlLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:urllookup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get URL lookup info from ZScaler for a given URL';

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
        // get values from environment file
        $zscaler_key = GETENV('ZSCALER_API_KEY');
        $zscaler_username = GETENV('ZSCALER_USERNAME');
        $zscaler_password = GETENV('ZSCALER_PASSWORD');
        $zscaler_uri = GETENV('ZSCALER_URI');

        // calculate obfuscate timestamp and API key
        $obfuscateTimestamp = (int)(microtime(true) * 1000);
        $obfuscatedApiKey = $this->obfuscateApiKey($obfuscateTimestamp, $zscaler_key);

        Log::info('[GetZScalerUrlCategories.php] obfuscated API key: '.$obfuscatedApiKey);
        Log::info('[GetZScalerUrlCategories.php] obfuscated timestamp: '.$obfuscateTimestamp);

        // setup cookie jar
        $cookiejar = storage_path('app/cookies/zscaler.txt');

        // instantiate crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup headers
        $headers = [
            'Content-Type: application/json'
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // build post body and convert to JSON
        $post_data = [
            'apiKey'    => $obfuscatedApiKey,
            'username'  => $zscaler_username,
            'password'  => $zscaler_password,
            'timestamp' => $obfuscateTimestamp
        ];
        $post_data_json = \Metaclassing\Utility::encodeJson($post_data);
        //Log::info('[GetZScalerUrlCategories.php] post data JSON: '.$post_data_json);

        // post to authenticate session, capture response and dump it to file
        $response_json = $crawler->post($zscaler_uri.'/authenticatedSession', '', $post_data_json);
        file_put_contents(storage_path('app/responses/zscaler_auth.response'), $response_json);

        // attempt to JSON decode response
        $response = \Metaclassing\Utility::decodeJson($response_json);

        // check that authType exists and matches what we expect
        if ($response['authType'] == 'ADMIN_LOGIN') {

            // grab the cookie from the cookie jar
            $api_cookie = file_get_contents(storage_path('app/cookies/zscaler.txt'));

            // attempt to use regex to parse out the JESSIONID value
            if (preg_match('/(.*\n)*.*JSESSIONID\s+(\w+)$/mi', $api_cookie, $matches)) {
                Log::info('[GetZScalerUrlCategories.php] pattern matched JSESSIONID value: '.$matches[2]);

                // instantiate new crawler object
                $crawler = new \Crawler\Crawler($cookiejar);

                // setup headers with cookie value
                $headers = [
                    'Content-Type: application/json',
                    'Cookie: JSESSIONID='.$matches[2]
                ];
                curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

                $post_body = [
                    'koskey.net'
                ];
                $post_body_json = \Metaclassing\Utility::encodeJson($post_body);

                Log::info('[GetZScalerUrlLookup.php] domain(s) to submit to UrlLookup: '.$post_body_json);

                // send request for URL categories, capture response and dump to file
                //$response_json = $crawler->get($zscaler_uri.'/urlCategories?customOnly=true');

                $response_json = $crawler->post($zscaler_uri.'/urlLookup', '', $post_body_json);
                file_put_contents(storage_path('app/responses/url_lookup.response'), $response_json);

            } else {
                Log::error('[GetZScalerUrlCategories.php] could not match JSESSIONID value in cookie');
            }
        }
    }


    /**
     * @param int $obfuscateTimestamp in MICROseconds
     * @param string $apiKey
     * @return string
     */
    private static function obfuscateApiKey($obfuscateTimestamp, $apiKey)
    {
        $n = substr($obfuscateTimestamp, -6);
        $r = $n >> 1;
        $r = str_pad($r, 6, '0', STR_PAD_LEFT);
        $obfuscatedApiKey = '';
        for ($i = 0; $i < strlen($n); $i++) {
            $pos = substr($n, $i, 1);
            $obfuscatedApiKey .= substr($apiKey, $pos, 1);
        }
        for ($j = 0; $j < strlen($r); $j++) {
            $pos = substr($r, $j, 1) + 2;
            $obfuscatedApiKey .= substr($apiKey, $pos, 1);
        }
        return $obfuscatedApiKey;
    }
}
