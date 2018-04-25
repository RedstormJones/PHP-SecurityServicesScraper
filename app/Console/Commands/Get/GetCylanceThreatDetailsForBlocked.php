<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCylanceThreatDetailsForBlocked extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:blockedthreatdetails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get details of blocked Cylance threats.';

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
        /************************************************************
         * [1] Authenticate to Cylance and navigate to Threats page *
         ************************************************************/

        Log::info(PHP_EOL.PHP_EOL.'****************************************************'.PHP_EOL.'* Starting Cylance blocked threat details crawler! *'.PHP_EOL.'****************************************************');

        $crawler = $this->authenticateToCylance();

        /***************************************
         * [2] Query for active threat details *
         ***************************************/

        // change url to point to the threat list
        $url = 'https:/'.'/protect.cylance.com/Threats/ListThreatsOverview';

        // setup collection array and variables for paging
        $collection = [];
        $i = 0;
        $page = 1;
        $page_size = 1000;

        // setup necessary post data
        $post = [
            'sort'      => 'ActiveInDevices-desc',
            'page'      => $page,
            'pageSize'  => $page_size,
            'group'     => '',
            'aggregate' => '',
            'filter'    => '',
        ];

        Log::info('[+] starting query loop');

        do {
            Log::info('[+] querying for page '.$page);

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, '', $this->postArrayToString($post));

            // dump raw response to threats.dump.* file where * is the page number
            file_put_contents(storage_path('app/responses/threats.dump.'.$page), $response);

            // json decode the response
            $threats = json_decode($response, true);

            // save this pages response array to our collection
            $collection[] = $threats['Data'];

            // set count to the total number of devices returned with each response.
            // this should not change from response to response
            $count = $threats['Total'];

            Log::info('[+] query for page '.$page.' complete - got '.count($threats['Data']).' threats');

            $i += $page_size;   // increment i by page_size
            $page++;            // increment the page number

            sleep(1);   // wait a second before hammering on their webserver again
        } while ($i < $count);

        // collapse collection into a simple array ( ex. [[1,2,3],[4,5,6],[7,8]] ==> [1,2,3,4,5,6,7,8] )
        $threats_collapsed = array_collapse($collection);
        Log::info('[+] count of threats collected: '.count($threats_collapsed));

        // turn threats collection into a Laravel Collection
        $threats = collect($threats_collapsed);
        Log::info('[+] count of threats in new collection: '.$threats->count());

        // pluck threat id's from collection where threat is seen active
        $threat_ids = $threats->where('BlockedInDevices', '>', 0)->pluck('Id')->all();
        Log::info('[+] count of plucked threat id\'s: '.count($threat_ids));

        // used to save off threat id-specific collections to be aggregated later
        $blocked_threat_details = [];

        // setup url
        $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesBlocked?filehashId=';

        // cycle through threat id's and query for active threat details
        foreach ($threat_ids as $threat_id) {
            Log::info('querying blocked threats for threat id: '.$threat_id);

            // setup collection array and variables for paging
            $collection = [];
            $i = 0;
            $page = 1;
            $page_size = 100;

            do {
                // setup post data
                $post = [
                    'sort'      => '',
                    'page'      => $page,
                    'pageSize'  => $page_size,
                    'group'     => '',
                    'aggregate' => '',
                    'filter'    => '',
                ];

                // post data to webpage and capture response
                $response = $crawler->post(($url.$threat_id), '', $this->postArrayToString($post));
                file_put_contents(storage_path('app/responses/blocked_threat_details.response'), $response);

                // json decode the response
                $threats = \Metaclassing\Utility::decodeJson($response);

                // if the response contains errors pop smoke and die
                if ($threats['Errors']) {
                    Log::error('[!] Error: Cylance API is returning errors:: '.$threat['Errors']);
                    die('[!] Error: Cylance API is returning errors:: '.$threat['Errors'].PHP_EOL);
                } else {
                    // otherwise, add this page's response array to our collection
                    $collection[] = $threats['Data'];
                }

                // set count to the total number of records returned (should not change from response to response)
                $count = $threats['Total'];
                Log::info('[+] count of blocked threat records found: '.count($threats['Data']));

                $i += $page_size;   // increment i by page_size
                $page++;            // increment the page number

                // wait a second before hammering on their webserver again
                sleep(1);
            } while ($i < $count);

            // collapse collection into a simple array ( ex. [[1,2,3],[4,5,6],[7,8]] ==> [1,2,3,4,5,6,7,8] )
            $threats = array_collapse($collection);

            // save off threat collection for this threat id
            $blocked_threat_details[] = $threats;
        }

        // collapse collection into a simple array ( ex. [[1,2,3],[4,5,6],[7,8]] ==> [1,2,3,4,5,6,7,8] )
        $blocked_threats_collection = array_collapse($blocked_threat_details);

        // log count
        Log::info('[+] total blocked threat collection count: '.count($blocked_threats_collection));

        $device_threat_details = [];

        // cycle through blocked threats
        foreach ($blocked_threats_collection as $data) {
            // format datetimes
            $added = $this->stringToDate($data['Added']);
            $first_found = $this->stringToDate($data['FirstFound']);
            $offline_date = $this->stringToDate($data['OfflineDate']);

            $device_files = [];

            // cycle through device files
            foreach ($data['DeviceFiles'] as $device_file) {
                // format first seen datetime
                $first_seen = $this->stringToDate($device_file['FirstSeen']);

                // build device files array
                $device_files[] = [
                    'FilePath'              => $device_file['FilePath'],
                    'DriveType'             => $device_file['DriveType'],
                    'FirstSeen'             => $first_seen,
                    'AutoRun'               => $device_file['AutoRun'],
                    'IsRunning'             => $device_file['IsRunning'],
                    'AgentEventId'          => $device_file['AgentEventId'],
                    'OpticsRequestId'       => $device_file['OpticsRequestId'],
                    'VDataId'               => $device_file['VDataId'],
                    'OpticsRequestStatus'   => $device_file['OpticsRequestStatus'],
                ];
            }

            // build final array
            $device_threat_details[] = [
                'AgentEventId'          => $data['AgentEventId'],
                'VDataId'               => $data['VDataId'],
                'OpticsRequestId'       => $data['OpticsRequestId'],
                'IsOpticsButtonEnabled' => $data['IsOpticsButtonEnabled'],
                'OpticsButtonHoverText' => $data['OpticsButtonHoverText'],
                'OpticsButtonText'      => $data['OpticsButtonText'],
                'DeviceId'              => $data['DeviceId'],
                'DeviceName'            => $data['DeviceName'],
                'Added'                 => $added,
                'FirstFound'            => $first_found,
                'OfflineDate'           => $offline_date,
                'PolicyName'            => $data['PolicyName'],
                'AgentVersion'          => $data['AgentVersion'],
                'IsOffline'             => $data['IsOffline'],
                'FilesAnalyzed'         => $data['FilesAnalyzed'],
                'Unsafe'                => $data['Unsafe'],
                'Abnormal'              => $data['Abnormal'],
                'Quarantined'           => $data['Quarantined'],
                'Waived'                => $data['Waived'],
                'ExploitAttempts'       => $data['ExploitAttempts'],
                'OperatingSystem'       => $data['OperatingSystem'],
                'ClientStatus'          => $data['ClientStatus'],
                'DeviceFiles'           => $device_files,
                'Zones'                 => $data['Zones'],
                'IpAddresses'           => $data['IpAddresses'],
                'MacAddresses'          => $data['MacAddresses'],
                'Users'                 => $data['Users'],
                'AutoRun'               => $data['AutoRun'],
                'IsRunning'             => $data['IsRunning'],
                'DetectedBy'            => $data['DetectedBy'],
                'BackgroundDetection'   => $data['BackgroundDetection'],
                'IsSafe'                => $data['IsSafe'],
                'DeviceFilesText'       => $data['DeviceFilesText'],
                'FileName'              => $data['FileName'],
                'ZonesText'             => $data['ZonesText'],
                'IPAddressesText'       => $data['IPAddressesText'],
                'MacAddressesText'      => $data['MacAddressesText'],
                'UsersText'             => $data['UsersText'],
                'OpticsClientStatus'    => $data['OpticsClientStatus'],
                'OpticsV2FocusStatus'   => $data['OpticsV2FocusStatus'],
                'OpticsV2FocusId'       => $data['OpticsV2FocusId'],
            ];
        }

        file_put_contents(storage_path('app/collections/blocked_threat_details.json'), \Metaclassing\Utility::encodeJson($device_threat_details));

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate a Kafka producer
        $producer = new \Kafka\Producer();

        // cycle through final array
        foreach ($device_threat_details as $threat_detail) {
            // create unique id for upsert-ing
            $threat_detail['ThreatDetailId'] = $threat_detail['DeviceId'].'_'.$threat_detail['FileName'];

            // add upsert datetime
            $threat_detail['UpsertDate'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'cylance_threat_details_blocked',
                    'value' => \Metaclassing\Utility::encodeJson($threat_detail),
                ],
            ]);

            // check for and log erros
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$threat_detail['DeviceId']);
            }
        }

        /*
            $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
            $crawler = new \Crawler\Crawler($cookiejar);

            $headers = [
                'Content-Type: application/json',
            ];

            // setup curl HTTP headers with $headers
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            foreach ($device_threat_details as $device_threat) {
                $url = 'http://10.243.32.36:9200/cylance_threat_details_blocked/cylance_threat_details_blocked/';
                Log::info('HTTP Post to elasticsearch: '.$url);

                $post = [
                    'doc'   => $device_threat,
                ];

                $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

                // log the POST response then JSON decode it
                $response = \Metaclassing\Utility::decodeJson($json_response);
                Log::info($response);

                if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                    Log::info('Cylance device threat was successfully inserted into ES: '.$device_threat['DeviceId']);
                } else {
                    Log::error('Something went wrong inserting device: '.$device_threat['DeviceId']);
                    die('Something went wrong inserting device: '.$device_threat['DeviceId'].PHP_EOL);
                }
            }
        */

        Log::info('* Cylance blocked threat details completed! *');
    }

    /**
     * Function to authenticate to the Cylance console.
     *
     * @return mixed
     */
    public function authenticateToCylance()
    {
        $username = getenv('CYLANCE_USERNAME');
        $password = getenv('CYLANCE_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup file to hold cookie
        $cookiejar = storage_path('app/cookies/cylance_cookie.txt');

        // create crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // set login URL
        $url = 'https:/'.'/login.cylance.com/Login';

        // hit login page and capture response
        $response = $crawler->get($url);

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
                file_put_contents($response_path.'cylancethreats_error.dump', $response);

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

        // once you're logged in hit the Threats page
        $url = 'https:/'.'/protect.cylance.com/Threats';
        $response = $crawler->get($url);

        // look for javascript token on Threats page
        $regex = '/var\s+token\s+=\s+"(.+)"/';

        // if we find the javascript token then set it to $token
        if (preg_match($regex, $response, $hits)) {
            $token = $hits[1];
        } else {
            // otherwise pop smoke and die
            Log::error('Error: could not get javascript token');
            die('Error: could not get javascript token crap'.PHP_EOL);
        }

        // use javascript token to setup necessary HTTP headers
        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Request-Verification-Token: '.$token,
            'X-Requested-With: XMLHttpRequest',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        return $crawler;
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
            $datetime_pieces = explode(' ', $datetime);
            $date_time = $datetime_pieces[0].'T'.$datetime_pieces[1];
        } else {
            $date_time = null;
        }

        return $date_time;
    }
}
