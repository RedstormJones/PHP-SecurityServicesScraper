<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\Cylance\CylanceThreat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCylanceThreats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:cylancethreats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new Cylance threats';

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
         * [1] Get all Cylance threats *
         *******************************/

        Log::info(PHP_EOL.PHP_EOL.'*************************************'.PHP_EOL.'* Starting Cylance threats crawler! *'.PHP_EOL.'*************************************');

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

        Log::info('starting page scrape loop');

        do {
            Log::info('scraping loop for page '.$page);

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, '', $this->postArrayToString($post));

            // dump raw response to threats.dump.* file where * is the page number
            file_put_contents($response_path.'threats.dump.'.$page, $response);

            // json decode the response
            $threats = json_decode($response, true);

            // save this pages response array to our collection
            $collection[] = $threats;

            // set count to the total number of devices returned with each response.
            // this should not change from response to response
            $count = $threats['Total'];

            Log::info('scrape for page '.$page.' complete - got '.count($threats['Data']).' threats');

            $i += $page_size;   // increment i by page_size
            $page++;            // increment the page number

            sleep(1);   // wait a second before hammering on their webserver again
        } while ($i < $count);

        $cylance_threats = [];

        // first level is simple sequencail array of 1,2,3
        foreach ($collection as $response) {
            // next level down is associative, the KEY we care about is 'Data'
            $results = $response['Data'];
            foreach ($results as $threat) {
                // this is confusing logic.
                $cylance_threats[] = $threat;
            }
        }

        $cylance_threats_final = [];

        foreach ($cylance_threats as $threat) {
            // format datetimes for threat record
            $first_found = $this->stringToDate($threat['FirstFound']);
            $last_found = $this->stringToDate($threat['LastFound']);
            $active_last_found = $this->stringToDate($threat['ActiveLastFound']);
            $allowed_last_found = $this->stringToDate($threat['AllowedLastFound']);
            $blocked_last_found = $this->stringToDate($threat['BlockedLastFound']);
            $suspicious_last_found = $this->stringToDate($threat['SuspiciousLastFound']);
            $cert_timestamp = $this->stringToDate($threat['CertTimeStamp']);

            $cylance_threats_final[] = [
                'IsRunning'             => $threat['IsRunning'],
                'DetectorId'            => $threat['DetectorId'],
                'CertTimeStamp'         => $cert_timestamp,
                'BlockedLastFound'      => $blocked_last_found,
                'CheckboxId'            => $threat['CheckboxId'],
                'Signed'                => $threat['Signed'],
                'AutoRun'               => $threat['AutoRun'],
                'IsUniqueToCylance'     => $threat['IsUniqueToCylance'],
                'SubClassification'     => $threat['SubClassification'],
                'VirusTotal'            => $threat['VirusTotal'],
                'FirstFound'            => $first_found,
                'Classification'        => $threat['Classification'],
                'CertPublisher'         => $threat['CertPublisher'],
                'ActiveInDevices'       => $threat['ActiveInDevices'],
                'CertIssuer'            => $threat['CertIssuer'],
                'CylanceScore'          => $threat['CylanceScore'],
                'AllowedInDevices'      => $threat['AllowedInDevices'],
                'ThreatId'              => $threat['Id'],
                'VirusTotalText'        => $threat['VirusTotalText'],
                'BlockedInDevices'      => $threat['BlockedInDevices'],
                'ActiveLastFound'       => $active_last_found,
                'MD5'                   => $threat['MD5'],
                'DetectedBy'            => $threat['DetectedBy'],
                'Detector'              => $threat['Detector'],
                'SuspiciousInDevices'   => $threat['SuspiciousInDevices'],
                'SuspiciousLastFound'   => $suspicious_last_found,
                'IsVirusTotalThreat'    => $threat['IsVirusTotalThreat'],
                'GlobalListType'        => $threat['GlobalListType'],
                'CurrentModel'          => $threat['CurrentModel'],
                'ThreatPriority'        => $threat['ThreatPriority'],
                'FileSize'              => $threat['FileSize'],
                'FileHashIconId'        => $threat['FileHashIconId'],
                'LastFound'             => $last_found,
                'IsSafelisted'          => $threat['IsSafelisted'],
                'IsGlobalQuarantined'   => $threat['IsGlobalQuarantined'],
                'ThreatPriorityValue'   => $threat['ThreatPriorityValue'],
                'FileHashId'            => $threat['FileHashId'],
                'FileType'              => $threat['FileType'],
                'NewModel'              => $threat['NewModel'],
                'AllowedLastFound'      => $allowed_last_found,
                'Priority'              => $threat['Priority'],
                'CommonName'            => $threat['CommonName'],
                'FullClassification'    => $threat['FullClassification'],
                'Infinity'              => $threat['Infinity'],
            ];
        }

        Log::info('threats successfully collected: '.count($cylance_threats));

        // JSON encode and dump devices array to file
        file_put_contents(storage_path('app/collections/cylance_threats.json'), \Metaclassing\Utility::encodeJson($cylance_threats_final));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        // cycle through Cylance threats
        foreach ($cylance_threats_final as $cylance_threat) {
            $result = $producer->send([
                [
                    'topic' => 'cylance_threats',
                    'value' => \Metaclassing\Utility::encodeJson($cylance_threat),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$cylance_threat['ThreatId']);
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

        foreach ($cylance_threats_final as $threat) {
            $url = 'http://10.243.32.36:9200/cylance_threats/cylance_threats/'.$threat['ThreatId'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $threat,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            // log the POST response then JSON decode it
            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('Cylance threat was successfully inserted into ES: '.$threat['ThreatId']);
            } else {
                Log::error('Something went wrong inserting device: '.$threat['ThreatId']);
                die('Something went wrong inserting device: '.$threat['ThreatId'].PHP_EOL);
            }
        }
        */

        /*************************************
         * [2] Process threats into database *
         *************************************/

        /*
        Log::info(PHP_EOL.'****************************************'.PHP_EOL.'* Starting Cylance threats processing! *'.PHP_EOL.'****************************************');

        foreach ($cylance_threats_final as $threat) {
            Log::info('processing Cylance threat: '.$threat['CommonName']);

            $cylance_threat = CylanceThreat::withTrashed()->updateOrCreate(
                [
                    'threat_id'                => $threat['ThreatId'],
                ],
                [
                    'common_name'              => $threat['CommonName'],
                    'cylance_score'            => $threat['CylanceScore'],
                    'active_in_devices'        => $threat['ActiveInDevices'],
                    'allowed_in_devices'       => $threat['AllowedInDevices'],
                    'blocked_in_devices'       => $threat['BlockedInDevices'],
                    'suspicious_in_devices'    => $threat['SuspiciousInDevices'],
                    'first_found'              => $first_found,
                    'last_found'               => $last_found,
                    'last_found_active'        => $active_last_found,
                    'last_found_allowed'       => $allowed_last_found,
                    'last_found_blocked'       => $blocked_last_found,
                    'md5'                      => $threat['MD5'],
                    'virustotal'               => $threat['VirusTotal'],
                    'is_virustotal_threat'     => $threat['IsVirusTotalThreat'],
                    'full_classification'      => $threat['FullClassification'],
                    'is_unique_to_cylance'     => $threat['IsUniqueToCylance'],
                    'is_safelisted'            => $threat['IsSafelisted'],
                    'detected_by'              => $threat['DetectedBy'],
                    'threat_priority'          => $threat['ThreatPriority'],
                    'current_model'            => $threat['CurrentModel'],
                    'priority'                 => $threat['Priority'],
                    'file_size'                => $threat['FileSize'],
                    'global_quarantined'       => $threat['IsGlobalQuarantined'],
                    'signed'                   => $threat['Signed'],
                    'cert_issuer'              => $threat['CertIssuer'],
                    'cert_publisher'           => $threat['CertPublisher'],
                    'cert_timestamp'           => $cert_timestamp,
                    'data'                     => \Metaclassing\Utility::encodeJson($threat),
                ]
            );

            // touch threat model to update the 'updated_at' timestamp (in case nothing was changed)
            $cylance_threat->touch();

            // restore threat model to remove deleted_at timestamp
            $cylance_threat->restore();
        }

        // process soft deletes for old records
        $this->processDeletes();
        */

        Log::info('* Cylance threats completed! *'.PHP_EOL);
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
     * Function to soft delete expired threat records.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subHours(2);

        $threats = CylanceThreat::all();

        foreach ($threats as $threat) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $threat->updated_at);

            if ($updated_at->lt($delete_date)) {
                Log::info('deleting threat: '.$threat->common_name);
                $threat->delete();
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
            $datetime_pieces = explode(' ', $datetime);
            $date_time = $datetime_pieces[0].'T'.$datetime_pieces[1];
        } else {
            $date_time = null;
        }

        return $date_time;
    }
}
