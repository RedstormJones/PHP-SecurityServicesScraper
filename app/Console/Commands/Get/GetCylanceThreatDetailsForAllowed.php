<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\Cylance\CylanceThreat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Phpreboot\Stopwatch\StopWatch;

class GetCylanceThreatDetailsForAllowed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:allowedthreatdetails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get details of allowed Cylance threats.';

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

        Log::info(PHP_EOL.PHP_EOL.'****************************************************'.PHP_EOL.'* Starting Cylance allowed threat details crawler! *'.PHP_EOL.'****************************************************');

        // start a stopwatch
        $stopwatch = new StopWatch();
        $stopwatch->start();

        $crawler = $this->authenticateToCylance();

        /***************************************
         * [2] Query for active threat details *
         ***************************************/

        // used to save off threat id-specific collections to be aggregated later
        $allowed_threat_details = [];

        // pluck a collection of threat id's from the securitymetrics db
        $threat_ids = CylanceThreat::where('allowed_in_devices', '>', 0)->pluck('threat_id');

        Log::info('threat id count from securitymetrics db: '.$threat_ids->count());

        // cycle through threat id's and query for active threat details
        foreach ($threat_ids as $threat_id) {
            Log::info('querying allowed threats for threat id: '.$threat_id);

            // setup url
            $url = 'https:/'.'/protect.cylance.com/ThreatDetails/DevicesAllowed?filehashId='.$threat_id;

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
                $response = $crawler->post($url, '', $this->postArrayToString($post));
                file_put_contents(storage_path('app/responses/allowed_threat_details.response'), $response);

                // json decode the response
                $threats = \Metaclassing\Utility::decodeJson($response);

                // if the response contains errors pop smoke and die
                if ($threats['Errors']) {
                    Log::error('Error: Cylance API is returning errors:: '.$threat['Errors']);
                    die('Error: Cylance API is returning errors:: '.$threat['Errors'].PHP_EOL);
                } else {
                    // otherwise, add this page's response array to our collection
                    $collection[] = $threats;
                }

                // set count to the total number of records returned (should not change from response to response)
                $count = $threats['Total'];
                Log::info('count of threat details records received: '.count($threats['Data']));

                $i += $page_size;   // increment i by page_size
                $page++;            // increment the page number

                // wait a second before hammering on their webserver again
                //sleep(1);
            } while ($i < $count);

            /*
             * after enumerating all active threats for the current threat id extract the data from each
             * response in the collection array and add each object to the threat collection array
             */

            // used to build simple array of threat detail objects for a particular threat id
            $threat_collection = [];

            // first level is simple sequencail array of 1,2,3
            foreach ($collection as $response) {
                // next level down is associative, the KEY we care about is 'Data'
                $results = $response['Data'];

                foreach ($results as $threat) {
                    // this is confusing logic.
                    $threat_collection[] = $threat;
                }
            }

            // save off threat collection for this threat id
            $allowed_threat_details[] = $threat_collection;
        }

        // simple array to hold all threat detail objects collected in a simple array
        $allowed_threats_collection = [];

        foreach ($allowed_threat_details as $allowed_threats) {
            foreach ($allowed_threats as $threat) {
                $allowed_threats_collection[] = $threat;
            }
        }

        // log count
        Log::info('total allowed threat collection count: '.count($allowed_threats_collection));

        $device_threat_details = [];

        Log::info('converting millisecond timestamps to datetimes...');
        foreach ($allowed_threats_collection as $data) {
            $added = $this->stringToDate($data['Added']);
            $first_found = $this->stringToDate($data['FirstFound']);
            $offline_date = $this->stringToDate($data['OfflineDate']);

            $device_files = [];

            foreach ($data['DeviceFiles'] as $device_file) {
                $first_seen = $this->stringToDate($device_file['FirstSeen']);

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

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($device_threat_details as $device_threat) {
            if (preg_match('/\s/', $device_threat['FileName'])) {
                $filename = str_replace(' ', '', $device_threat['FileName']);
            } else {
                $filename = $device_threat['FileName'];
            }

            $es_id = $device_threat['DeviceId'].'-'.$filename;
            $url = 'http://10.243.32.36:9200/cylance_threat_details_allowed/cylance_threat_details_allowed/'.$es_id.'/_update';
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $device_threat,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            // log the POST response then JSON decode it
            Log::info($json_response);
            $response = \Metaclassing\Utility::decodeJson($json_response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('Cylance device threat was successfully inserted into ES: '.$device_threat['DeviceId']);
            } else {
                Log::error('Something went wrong inserting device: '.$device_threat['DeviceId']);
                die('Something went wrong inserting device: '.$device_threat['DeviceId'].PHP_EOL);
            }
        }

        file_put_contents(storage_path('app/collections/allowed_threat_details.json'), \Metaclassing\Utility::encodeJson($device_threat_details));

        // stop stopwatch, calculate hour/min/sec values and log execution time
        $stopwatch->stop();
        $execution_time = $stopwatch->getTime();
        $execution_secs = (int) ($execution_time % 60);
        $execution_mins = (int) ($execution_time / 60);
        $execution_hours = (int) ($execution_mins / 60);

        if ($execution_mins > 59) {
            $additional_hours = (int) ($execution_mins / 60);

            $execution_mins = (int) ($execution_mins % 60);
            $execution_hours += $additional_hours;
        }
        Log::info('Allowed threat details execution time: '.$execution_hours.':'.$execution_mins.':'.$execution_secs);

        Log::info('* Cylance allowed threat details completed! *');
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
                //Log::info('request verification token: '.$csrftoken);
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
            //Log::info('threats token: '.$token);
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
        } else {
            $datetime = null;
        }

        return $datetime;
    }
}