<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use App\Cylance\CylanceDevice;
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

        Log::info('Starting Cylance devices crawler!');

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
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // point url to the devices list API endpoint
        $url = 'https:/'.'/my-vs0.cylance.com/Grids/DevicesList_Ajax';

        // setup necessary post data
        $post = [
            'sort'      => 'Name-asc',
            'page'      => '1',
            'pageSize'  => '100',
            'group'     => '',
            'aggregate' => '',
            'filter'    => '',
        ];

        // setup collection array and variables for paging
        $collection = [];
        $i = 0;
        $page = 1;

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

            // set count to the total number of devices returned with each response.
            // this should not change from response to response
            $count = $devices['Total'];

            Log::info('scrape for page '.$page.' complete - got '.count($devices['Data']));

            $i += count($devices['Data']);  // Increase i by PAGESIZE!
            $page++;                        // Increase the page number

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

        Log::info('devices successfully collected: '.count($cylance_devices));

        // Now we have a simple array [1,2,3] of all the device records,
        // each device record is a key=>value pair collection / assoc array
        //\Metaclassing\Utility::dumper($threats);
        file_put_contents(storage_path('app/collections/devices.json'), \Metaclassing\Utility::encodeJson($cylance_devices));

        /*************************************
         * [2] Process devices into database *
         *************************************/

        Log::info('Starting Cylance devices processing!');

        $date_regex = '/\/Date\((\d+)\)\//';

        foreach ($cylance_devices as $device) {
            $exists = CylanceDevice::where('device_id', $device['DeviceId'])->value('id');

            // if the device record exists then update it, otherwise create a new one
            if ($exists) {
                // format datetimes for updating device record
                $created_date = $this->stringToDate($device['Created']);
                $offline_date = $this->stringToDate($device['OfflineDate']);

                $updated = CylanceDevice::where('id', $exists)->update([
                    'device_name'          => $device->Name,
                    'zones_text'           => $device->ZonesText,
                    'files_unsafe'         => $device->Unsafe,
                    'files_quarantined'    => $device->Quarantined,
                    'files_abnormal'       => $device->Abnormal,
                    'files_waived'         => $device->Waived,
                    'files_analyzed'       => $device->FilesAnalyzed,
                    'agent_version_text'   => $device->AgentVersionText,
                    'last_users_text'      => $device->LastUsersText,
                    'os_versions_text'     => $device->OSVersionsText,
                    'ip_addresses_text'    => $device->IPAddressesText,
                    'mac_addresses_text'   => $device->MacAddressesText,
                    'policy_name'          => $device->PolicyName,
                    'device_created_at'    => $created_date,
                    'device_offline_date'  => $offline_date,
                    'data'                 => json_encode($device),
                ]);

                // touch device model to update 'updated_at' timestamp (in case nothing was changed)
                $devicemodel = CylanceDevice::find($exists);

                $devicemodel->touch();

                Log::info('updated device: '.$device['Name']);
            } else {
                Log::info('creating device: '.$device['Name']);
                $this->createDevice($device);
            }
        }

        // process soft deletes for old records
        $this->processDeletes();
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
     * Create new CylanceDevice model.
     *
     * @return void
     */
    public function createDevice($device)
    {
        // format datetimes for new device record
        $created_date = $this->stringToDate($device['Created']);
        $offline_date = $this->stringToDate($device['OfflineDate']);

        $new_device = new CylanceDevice();

        $new_device->device_id = $device['DeviceId'];
        $new_device->device_name = $device['Name'];
        $new_device->zones_text = $device['ZonesText'];
        $new_device->files_unsafe = $device['Unsafe'];
        $new_device->files_quarantined = $device['Quarantined'];
        $new_device->files_abnormal = $device['Abnormal'];
        $new_device->files_waived = $device['Waived'];
        $new_device->files_analyzed = $device['FilesAnalyzed'];
        $new_device->agent_version_text = $device['AgentVersionText'];
        $new_device->last_users_text = $device['LastUsersText'];
        $new_device->os_versions_text = $device['OSVersionsText'];
        $new_device->ip_addresses_text = $device['IPAddressesText'];
        $new_device->mac_addresses_text = $device['MacAddressesText'];
        $new_device->policy_name = $device['PolicyName'];
        $new_device->device_created_at = $created_date;
        $new_device->device_offline_date = $offline_date;
        $new_device->data = json_encode($device);

        $new_device->save();
    }

    /**
     * Delete old CylanceDevice models.
     *
     * @return void
     */
    public function processDeletes()
    {
        // create new datetime object and subtract one day to get delete_date
        $today = new \DateTime('now');
        $delete_date = $today->format('Y-m-d');

        // get all the devices
        $devices = CylanceDevice::all();

        /*
        * For each device, get its updated_at timestamp, remove the time of day portion, and check
        * it against delete_date to determine if its a stale record or not. If yes, delete it.
        **/
        foreach ($devices as $device) {
            $updated_at = substr($device->updated_at, 0, -9);

            // if updated_at is less than or equal to delete_date then we soft delete the device
            if ($updated_at < $delete_date) {
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
            $datetime = date('Y-m-d H:i:s', (intval($date_hits[1]) / 1000));
        } else {
            $datetime = null;
        }

        return $datetime;
    }
}
