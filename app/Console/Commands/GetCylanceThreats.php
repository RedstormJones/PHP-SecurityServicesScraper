<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use App\Cylance\CylanceThreat;
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

        Log::info('Starting Cylance threats crawler!');

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
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // change url to point to the threat list
        $url = 'https:/'.'/my-vs0.cylance.com/Threats/ListThreatsOverview';

        // setup necessary post data
        $post = [
            'sort'      => 'ActiveInDevices-desc',
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

        Log::info('starting page scrape loop');

        do {
            Log::info('scraping loop for page '.$page);

            // set the post page to our current page number
            $post['page'] = $page;

            // post data to webpage and capture response, which is hopefully a list of devices
            $response = $crawler->post($url, 'https:/'.'/my-vs0.cylance.com/Threats', $this->postArrayToString($post));

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

            $i += 100;   // Increase i by PAGESIZE!
            $page++;    // Increase the page number

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

        Log::info('threats successfully collected: '.count($cylance_threats));

        // Now we ahve a simple array [1,2,3] of all the threat records,
        // each threat record is a key=>value pair collection / assoc array
        //\Metaclassing\Utility::dumper($threats);
        file_put_contents(storage_path('app/collections/threats.json'), \Metaclassing\Utility::encodeJson($cylance_threats));

        /*************************************
         * [2] Process threats into database *
         *************************************/

        Log::info('Starting Cylance threats processing!');

        foreach ($cylance_threats as $threat) {
            $exists = CylanceThreat::where('threat_id', $threat['Id'])->withTrashed()->value('id');

            if ($exists) {
                // format datetimes for updating threat record
                $first_found = $this->stringToDate($threat['FirstFound']);
                $last_found = $this->stringToDate($threat['LastFound']);
                $active_last_found = $this->stringToDate($threat['ActiveLastFound']);
                $allowed_last_found = $this->stringToDate($threat['AllowedLastFound']);
                $blocked_last_found = $this->stringToDate($threat['BlockedLastFound']);
                $cert_timestamp = $this->stringToDate($threat['CertTimeStamp']);

                $updated = CylanceThreat::where('id', $exists)->update([
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
                    'data'                     => json_encode($threat),
                ]);

                // touch threat model to update the 'updated_at' timestamp (in case nothing was changed)
                $threatmodel = CylanceThreat::find($exists);

                if ($threatmodel != null) {
                    $threatmodel->touch();

                    /*
                    * do a restore to set the 'deleted_at' timestamp back to NULL
                    * in case this threat model had been soft deleted at some point.
                    */
                    $threatmodel->restore();
                }

                Log::info('updated threat: '.$threat['CommonName']);
            } else {
                Log::info('creating threat: '.$threat['CommonName']);
                $this->createThreat($threat);
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
     * Function to create a new Cylance Threat model.
     *
     * @return void
     */
    public function createThreat($threat)
    {
        // format datetimes for new threat record
        $first_found = $this->stringToDate($threat['FirstFound']);
        $last_found = $this->stringToDate($threat['LastFound']);
        $active_last_found = $this->stringToDate($threat['ActiveLastFound']);
        $allowed_last_found = $this->stringToDate($threat['AllowedLastFound']);
        $blocked_last_found = $this->stringToDate($threat['BlockedLastFound']);
        $cert_timestamp = $this->stringToDate($threat['CertTimeStamp']);

        // create new Cylance threat record and assign values
        $new_threat = new CylanceThreat();

        $new_threat->threat_id = $threat['Id'];
        $new_threat->common_name = $threat['CommonName'];
        $new_threat->cylance_score = $threat['CylanceScore'];
        $new_threat->active_in_devices = $threat['ActiveInDevices'];
        $new_threat->allowed_in_devices = $threat['AllowedInDevices'];
        $new_threat->blocked_in_devices = $threat['BlockedInDevices'];
        $new_threat->suspicious_in_devices = $threat['SuspiciousInDevices'];
        $new_threat->first_found = $first_found;
        $new_threat->last_found = $last_found;
        $new_threat->last_found_active = $active_last_found;
        $new_threat->last_found_allowed = $allowed_last_found;
        $new_threat->last_found_blocked = $blocked_last_found;
        $new_threat->md5 = $threat['MD5'];
        $new_threat->virustotal = $threat['VirusTotal'];
        $new_threat->is_virustotal_threat = $threat['IsVirusTotalThreat'];
        $new_threat->full_classification = $threat['FullClassification'];
        $new_threat->is_unique_to_cylance = $threat['IsUniqueToCylance'];
        $new_threat->is_safelisted = $threat['IsSafelisted'];
        $new_threat->detected_by = $threat['DetectedBy'];
        $new_threat->threat_priority = $threat['ThreatPriority'];
        $new_threat->current_model = $threat['CurrentModel'];
        $new_threat->priority = $threat['Priority'];
        $new_threat->file_size = $threat['FileSize'];
        $new_threat->global_quarantined = $threat['IsGlobalQuarantined'];
        $new_threat->signed = $threat['Signed'];
        $new_threat->cert_issuer = $threat['CertIssuer'];
        $new_threat->cert_publisher = $threat['CertPublisher'];
        $new_threat->cert_timestamp = $cert_timestamp;
        $new_threat->data = json_encode($threat);

        $new_threat->save();
    }

    /**
     * Function to soft delete expired threat records.
     *
     * @return void
     */
    public function processDeletes()
    {
        $today = new \DateTime('now');
        $yesterday = $today->modify('-1 day');
        $delete_date = $yesterday->format('Y-m-d');

        $threats = CylanceThreat::all();

        foreach ($threats as $threat) {
            $updated_at = substr($threat->updated_at, 0, -9);

            if ($updated_at <= $delete_date) {
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
            $datetime = date('Y-m-d H:i:s', (intval($date_hits[1]) / 1000));
        } else {
            $datetime = null;
        }

        return $datetime;
    }
}
