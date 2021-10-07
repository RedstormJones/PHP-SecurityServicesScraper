<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetUsersAndIPsAdv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:usersandips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queries the Defender API for all machines and their associated users, enumerating users and internal and external IPs';

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
        try {

            // we need token
            $token = $this->GetDefenderAccessToken();

            // advanced hunting URI
            $adv_hunting_uri = 'https://api-us.securitycenter.windows.com/api/advancedqueries/run';

            // advanced hunting query that I didn't write
            $post_body = [
                'Query' => 'DeviceInfo | join (DeviceNetworkInfo) on DeviceId | where Timestamp > ago(1h) | where NetworkAdapterStatus == "Up" | where OnboardingStatus == "Onboarded" | where DeviceType == "Workstation" | distinct Timestamp, DeviceName | join kind=rightouter ( DeviceInfo | join (DeviceNetworkInfo) on DeviceId | where Timestamp > ago(1h) | where NetworkAdapterStatus == "Up" | where OnboardingStatus == "Onboarded" | where DeviceType == "Workstation" | summarize arg_max(Timestamp, *) by Timestamp, DeviceName) on $left.DeviceName == $right.DeviceName | extend logged_on_users = parse_json(LoggedOnUsers) | extend internal_ip = parse_json(IPAddresses) | project Timestamp, DeviceName, logged_on_users[0]["UserName"], internal_ip[0]["IPAddress"], PublicIP'
            ];
            $post_body = \Metaclassing\Utility::encodeJson($post_body);

            // cookie file for crawler
            $cookiejar = storage_path('app/cookies/secops_defender_cookie.txt');

            // curl object
            $crawler = new \Crawler\Crawler($cookiejar);

            // use token to build auth header and set to crawler
            $headers = [
                'Authorization: Bearer '.$token
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // post request, capture response and dump to file
            $json_response = $crawler->post($adv_hunting_uri, '', $post_body);
            file_put_contents(storage_path('app/responses/secops_defender_adv_hunt.response'), $json_response);

            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($json_response);

            // get results from response object and dump to file
            $results = $response['Results'];
            file_put_contents(storage_path('app/collections/users_and_ips.json'), \Metaclassing\Utility::encodeJson($results));

        } catch (\Exception $e) {
            Log::info('An error has occurred in function handle(): '.$e);
            die('An error has occurred in function handle(): '.$e);
        }

    }


    public function GetDefenderAccessToken() {
        try {
            // setup things needed for token
            $app_id = getenv('SECOPS_DEFENDER_APP_ID');
            $app_secret = getenv('SECOPS_DEFENDER_APP_SECRET');
            $resource_app_id_uri = 'https://api.securitycenter.microsoft.com';
            $o_auth_uri = getenv('MS_OAUTH_DEFENDER_TOKEN_ENDPOINT');

            // put things together for post request
            $post_body = 'client_id='.$app_id.'&client_secret='.$app_secret.'&grant_type=client_credentials&resource='.$resource_app_id_uri;

            // cookie file for crawler
            $cookiejar = storage_path('app/cookies/secops_defender_cookie.txt');

            // curl object
            $crawler = new \Crawler\Crawler($cookiejar);

            // post request, capture response and dump to file
            $auth_response = $crawler->post($o_auth_uri, '', $post_body);
            file_put_contents(storage_path('app/responses/secops_def_auth.response'), $auth_response);

            // JSON decode response
            $response = \Metaclassing\Utility::decodeJson($auth_response);

            // return access_token from response object
            return $response['access_token'];

        } catch (\Exception $e) {
            Log::info('An error has occurred in function GetDefenderAccessToken(): '.$e);
            die('An error has occurred in function GetDefenderAccessToken(): '.$e);
        }
    }
}
