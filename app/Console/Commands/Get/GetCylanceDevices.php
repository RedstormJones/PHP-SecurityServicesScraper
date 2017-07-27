<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\Cylance\CylanceDevice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCylanceDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:cylancedevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new Cylance devices';

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
        /*******************************
         * [1] Get all Cylance devices *
         *******************************/

        Log::info(PHP_EOL.PHP_EOL.'*************************************'.PHP_EOL.'* Starting Cylance devices crawler! *'.PHP_EOL.'*************************************');

        $username = getenv('CYLANCE_USERNAME');
        $password = getenv('CYLANCE_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup file to hold cookie
        $cookiejar = storage_path('app/cookies/cylancecookie.txt');

        // create crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // set login URL
        $url = 'https:/'.'/login.cylance.com/Login';

        // hit login page and capture response
        $response = $crawler->get($url);

        Log::info('logging in to: '.$url);

        // If we DONT get the dashboard then we need to try and login
        $regex = '/<title>CylancePROTECT \| Dashboard<\/title>/';
        $tries = 0;
        while (!preg_match($regex, $response, $hits) && $tries <= 3) {
            $regex = '/RequestVerificationToken" type="hidden" value="(.+?)"/';

            // if we find the RequestVerificationToken then assign it to $csrftoken
            if (preg_match($regex, $response, $hits)) {
                $csrftoken = $hits[1];
            } else {
                // otherwise, dump response and die
                file_put_contents($response_path.'cylance_login.dump', $response);

                Log::error('Error: could not extract CSRF token from response');
                die('Error: could not extract CSRF token from response!'.PHP_EOL);
            }

            // use csrftoken and credentials to create post data
            $post = [
                '__RequestVerificationToken'   => $csrftoken,
                'Email'                        => $username,
                'Password'                     => $password,
            ];

            // try and post login data to the website
            $response = $crawler->post($url, $url, $this->postArrayToString($post));

            // increment tries and set regex back to Dashboard title
            $tries++;
            $regex = '/<title>CylancePROTECT \| Dashboard<\/title>/';
        }
        // once out of the login loop, if $tries is >= to 3 then we couldn't get logged in
        if ($tries > 3) {
            Log::error('Error: could not post successful login within 3 attempts');
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // dump dashboard html to a file
        file_put_contents($response_path.'cylance_dashboard.dump', $response);

        // look for javascript token
        $regex = '/var\s+token\s+=\s+"(.+)"/';

        // if we find the javascript token then set it to $token
        if (preg_match($regex, $response, $hits)) {
            $token = $hits[1];
        } else {
            // otherwise die
            Log::error('Error: could not get javascript token');
            die('Error: could not get javascript token crap'.PHP_EOL);
        }

        // use javascript token to setup necessary HTTP headers
        $headers = [
            'X-Request-Verification-Token: '.$token,
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // point url to the devices list API endpoint
        $url = 'https:/'.'/protect.cylance.com/Grids/DevicesList_Ajax';

        // setup collection array and variables for paging
        $collection = [];
        $i = 0;
        $page = 1;
        $page_size = 1000;

        // setup necessary post data
        $post = [
            'sort'      => 'Name-asc',
            'page'      => $page,
            'pageSize'  => $page_size,
            'group'     => '',
            'aggregate' => '',
            'filter'    => '',
        ];

        // start paging
        Log::info('starting page scrape loop');
        do {
            Log::info('scraping loop for page '.$page);

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, '', $this->postArrayToString($post));

            // dump raw response to devices.dump.* file where * is the page number
            file_put_contents($response_path.'devices.dump.'.$page, $response);

            // json decode the response
            $devices = \Metaclassing\Utility::decodeJson($response);

            // save this pages response array to our collection
            $collection[] = $devices;

            // set count to the total number of devices returned with each response
            $count = $devices['Total'];

            Log::info('scrape for page '.$page.' complete - got '.count($devices['Data']).' devices');

            $i += $page_size;  // increment i by page_size
            $page++;           // increment the page number

            // wait a second before hammering on their webserver again
            sleep(1);
        } while ($i < $count);

        // instantiate cylance device list
        $cylance_devices = [];

        // first level is simple sequencial array of 1,2,3
        foreach ($collection as $response) {
            // next level down is associative, the KEY we care about is 'Data'
            $results = $response['Data'];
            foreach ($results as $device) {
                // this is confusing logic.
                $cylance_devices[] = $device;
            }
        }

        // build final array of Cylance devices with all necessary values converted to usable format
        $cylance_devices_final = [];
        $user_regex = '/\w+\\\(\w+\.\w+-*\w+)/';

        foreach ($cylance_devices as $device) {
            $user_hits = [];

            // format datetimes for updating device record
            $created_date = $this->stringToDate($device['Created']);
            $offline_date = $this->stringToDate($device['OfflineDate']);

            $created_date_pieces = explode(' ', $created_date);
            $createddate = $created_date_pieces[0].'T'.$created_date_pieces[1];

            // extract user from last users text
            preg_match($user_regex, $device['LastUsersText'], $user_hits);
            if (isset($user_hits[1])) {
                $last_user = ucwords(strtolower($user_hits[1]), '.');
            } else {
                $last_user = '';
            }

            $cylance_devices_final[] = [
                'DeviceLdapGroupMembership'     => $device['DeviceLdapGroupMembership'],
                'ZoneRoleText'                  => $device['ZoneRoleText'],
                'IsOffline'                     => $device['IsOffline'],
                'Name'                          => $device['Name'],
                'OSVersionsText'                => $device['OSVersionsText'],
                'LastUsersText'                 => $last_user,
                'DeviceId'                      => $device['DeviceId'],
                'OfflineDate'                   => $offline_date,
                'Created'                       => $createddate,
                'Waived'                        => $device['Waived'],
                'Unsafe'                        => $device['Unsafe'],
                'ScriptCount'                   => $device['ScriptCount'],
                'ZoneRole'                      => $device['ZoneRole'],
                'Abnormal'                      => $device['Abnormal'],
                'IsSafe'                        => $device['IsSafe'],
                'Zones'                         => $device['Zones'],
                'DeviceLdapDistinguishedName'   => $device['DeviceLdapDistinguishedName'],
                'IPAddressesText'               => $device['IPAddressesText'],
                'MemoryProtection'              => $device['MemoryProtection'],
                'IPAddresses'                   => $device['IPAddresses'],
                'MacAddressesText'              => $device['MacAddressesText'],
                'RequiresUpdate'                => $device['RequiresUpdate'],
                'ZonesText'                     => $device['ZonesText'],
                'ClientStatus'                  => $device['ClientStatus'],
                'AgentVersionText'              => $device['AgentVersionText'],
                'PolicyName'                    => $device['PolicyName'],
                'FilesAnalyzed'                 => $device['FilesAnalyzed'],
                'Quarantined'                   => $device['Quarantined'],
                'BackgroundDetection'           => $device['BackgroundDetection'],
                'DnsName'                       => $device['DnsName'],
            ];
        }

        Log::info('devices successfully collected: '.count($cylance_devices_final));

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($cylance_devices_final as $device) {
            $url = 'http://10.243.32.36:9200/cylance_devices/cylance_devices/'.$device['DeviceId'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $device,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('Cylance device was successfully inserted into ES: '.$device['DeviceId']);
            } else {
                Log::error('Something went wrong inserting device: '.$device['DeviceId']);
                die('Something went wrong inserting device: '.$device['DeviceId'].PHP_EOL);
            }
        }

        // JSON encode and dump devices array to file
        file_put_contents(storage_path('app/collections/cylance_devices.json'), \Metaclassing\Utility::encodeJson($cylance_devices_final));

        /*************************************
         * [2] Process devices into database *
         *************************************/

        Log::info(PHP_EOL.'****************************************'.PHP_EOL.'* Starting Cylance devices processing! *'.PHP_EOL.'****************************************');

        foreach ($cylance_devices_final as $device) {
            Log::info('processing Cylance device: '.$device['Name']);

            // update model if it exists, otherwise create it
            $device_model = CylanceDevice::withTrashed()->updateOrCreate(
                [
                    'device_id'            => $device['DeviceId'],
                ],
                [
                    'device_name'          => $device['Name'],
                    'zones_text'           => $device['ZonesText'],
                    'files_unsafe'         => $device['Unsafe'],
                    'files_quarantined'    => $device['Quarantined'],
                    'files_abnormal'       => $device['Abnormal'],
                    'files_waived'         => $device['Waived'],
                    'files_analyzed'       => $device['FilesAnalyzed'],
                    'agent_version_text'   => $device['AgentVersionText'],
                    'last_users_text'      => $device['LastUsersText'],
                    'os_versions_text'     => $device['OSVersionsText'],
                    'ip_addresses_text'    => $device['IPAddressesText'],
                    'mac_addresses_text'   => $device['MacAddressesText'],
                    'policy_name'          => $device['PolicyName'],
                    'device_created_at'    => $device['Created'],
                    'device_offline_date'  => $device['OfflineDate'],
                    'data'                 => \Metaclassing\Utility::encodeJson($device),
                ]
            );

            // touch device model to update 'updated_at' timestamp (in case nothing was changed)
            $device_model->touch();

            // restore device model to remove deleted_at timestamp
            $device_model->restore();
        }

        // process soft deletes for old records
        $this->processDeletes();

        Log::info('* Cylance devices completed! *'.PHP_EOL);
    }

    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];
        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.$value;
        }

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }

    /**
     * Delete old CylanceDevice models.
     *
     * @return void
     */
    public function processDeletes()
    {
        // create new datetime object and subtract one day to get delete_date
        $delete_date = Carbon::now()->subHours(2);

        // get all the devices
        $devices = CylanceDevice::all();

        /*
        * For each device, get its updated_at timestamp, remove the time of day portion, and check
        * it against delete_date to determine if its a stale record or not. If yes, delete it.
        */
        foreach ($devices as $device) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $device->updated_at);

            // if updated_at is less than or equal to delete_date then we soft delete the device
            if ($updated_at->lt($delete_date)) {
                Log::info('deleting device: '.$device->device_name);
                $device->delete();
            }
        }
    }

    /**
     * Function to convert string timestamps to datetimes.
     *
     * @return string
     */
    public function stringToDate($date_str)
    {
        if ($date_str != null) {
            $date_regex = '/\/Date\((\d+)\)\//';
            preg_match($date_regex, $date_str, $date_hits);

            $datetime = Carbon::createFromTimestamp(intval($date_hits[1]) / 1000)->toDateTimeString();
        } else {
            $datetime = null;
        }

        return $datetime;
    }
}
