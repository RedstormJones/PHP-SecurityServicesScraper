<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetFMCDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:fmcdevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get FirePower Management Console devices';

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
        Log::info(PHP_EOL.PHP_EOL.'################################'.PHP_EOL.'# Starting FMC devices client! #'.PHP_EOL.'################################');

        $cookiejar = storage_path('app/cookies/fmc_auth_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $auth_str = getenv('FMC_AUTH');
        Log::info('[+] FMC auth str: '.$auth_str);

        //$fmc_explorer_url = getenv('FMC_URL').'/api/api-explorer/';
        $fmc_auth_url = getenv('FMC_URL').'/api/fmc_platform/v1/auth/generatetoken';
        Log::info('[+] FMC auth url: '.$fmc_auth_url);

        $headers = [
            'Authorization: Basic '.$auth_str,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($crawler->curl, CURLOPT_HEADER, 1);
        curl_setopt($crawler->curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($crawler->curl, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($crawler->curl, CURLOPT_CAPATH, '/var/www/storage/app/certs');
        curl_setopt($crawler->curl, CURLOPT_CAINFO, '/var/www/storage/app/certs/Firepower-Cert.pem');
        //curl_setopt($crawler->curl, CURLOPT_SSLCERT, '/var/www/storage/app/certs/Firepower-Cert.pem');
        curl_setopt($crawler->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crawler->curl, CURLOPT_VERBOSE, true);

        $json_response = $crawler->post($fmc_auth_url);
        file_put_contents(storage_path('app/responses/fmc-auth-response.txt'), $json_response);

        $header_size = curl_getinfo($crawler->curl, CURLINFO_HEADER_SIZE);
        Log::info('[+] FMC auth response header size: '.strval($header_size));

        // close auth connection to FMC
        curl_setopt($crawler->curl, CURLOPT_HEADER, 0);
        curl_close($crawler->curl);

        $headers_str = trim(substr($json_response, 0, $header_size));
        file_put_contents(storage_path('app/responses/fmc-auth-headers-response.txt'), $headers_str);

        // setup token regex's
        $access_token_regex = '/^.*?X-auth-access-token\:\s(.*).*$/miu';
        $refresh_token_regex = '/^.*?X-auth-refresh-token\:\s(.*).*$/miu';

        // parse out the access token from the response headers
        if (preg_match($access_token_regex, $headers_str, $hits)) {
            $access_token = $hits[1];
            Log::info('[+] FMC access token: '.$access_token);
        } else {
            Log::error('[!] could not get access token from response headers..');
            die('[!] could not get access token from response headers..'.PHP_EOL);
        }

        // parse out the refresh token from the response headers
        if (preg_match($refresh_token_regex, $headers_str, $hits)) {
            $refresh_token = $hits[1];
            Log::info('[+] FMC refresh token: '.$refresh_token);
        } else {
            Log::error('[!] could not get refresh token from response headers..');
            die('[!] could not get refresh token from response headers..'.PHP_EOL);
        }

        $fmc_devices_url = getenv('FMC_URL').':443/api/fmc_config/v1/domain/'.getenv('FMC_DOMAIN_ID').'/devices/devicerecords?objectId=*';
        Log::info('[+] FMC devices url: '.$fmc_devices_url);

        $cookiejar = storage_path('app/cookies/fmc_devices_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-auth-access-token: '.$access_token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);
        //curl_setopt($crawler->curl, CURLOPT_HEADER, 1);
        curl_setopt($crawler->curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($crawler->curl, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($crawler->curl, CURLOPT_CAPATH, '/var/www/storage/app/certs');
        //curl_setopt($crawler->curl, CURLOPT_CAINFO, '/var/www/storage/app/certs/Firepower-Cert.pem');
        //curl_setopt($crawler->curl, CURLOPT_SSLCERT, '/var/www/storage/app/certs/Firepower-Cert.pem');
        curl_setopt($crawler->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crawler->curl, CURLOPT_VERBOSE, true);

        $json_response = $crawler->get($fmc_devices_url);
        file_put_contents(storage_path('app/responses/fmc-devices-response.json'), $json_response);

        Log::info(PHP_EOL.PHP_EOL.'##########################'.PHP_EOL.'# Completed FMC devices! #'.PHP_EOL.'##########################');
    }
}
