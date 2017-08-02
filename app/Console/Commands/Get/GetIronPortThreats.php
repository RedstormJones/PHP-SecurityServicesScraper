<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\IronPort\IronPortThreat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetIronPortThreats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:ironportthreats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new IronPort threats';

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
        /*
         * [1] Get IronPort threats
         */

        Log::info(PHP_EOL.PHP_EOL.'**************************************'.PHP_EOL.'* Starting IronPort threats crawler! *'.PHP_EOL.'**************************************');

        $username = getenv('IRONPORT_USERNAME');
        $password = getenv('IRONPORT_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup cookiejar file
        $cookiejar = storage_path('app/cookies/ironport_cookie.txt');

        // instantiate crawler object
        $crawler = new \Crawler\Crawler($cookiejar);

        // set url
        $url = getenv('IRONPORT_SMA');

        // hit webpage and try to capture CSRF token, otherwise die
        $response = $crawler->get($url);

        // set regex string to dashboard page <title> element
        $regex = '/(<title>        Cisco         Content Security Management Appliance   M804 \(dh1146-sma1\.iphmx\.com\) -         Centralized Services &gt; System Status <\/title>)/';
        $tries = 0;
        // while NOT at the dashboard page
        while (!preg_match($regex, $response, $hits) && $tries <= 3) {
            // find CSRFKey value
            $regex = '/CSRFKey=([\w-]+)/';

            if (preg_match($regex, $response, $hits)) {
                $csrftoken = $hits[1];
            } else {
                Log::error('Error: could not get CSRF token');
                die('Error: could not get CSRF token'.PHP_EOL);
            }

            // set login URL and post data
            $url = getenv('IRONPORT_SMA').'/login';

            $post = [
                'action'    => 'Login',
                'referrer'  => getenv('IRONPORT_SMA').'/default',
                'screen'    => 'login',
                'username'  => $username,
                'password'  => $password,
            ];

            // try to login
            $response = $crawler->post($url, $url, $this->postArrayToString($post));

            // increment tries and set regex back to dashboard <title>
            $tries++;
            $regex = '/(<title>        Cisco         Content Security Management Appliance   M804 \(dh1146-sma1\.iphmx\.com\) -         Centralized Services &gt; System Status <\/title>)/';
        }
        // once out of the login loop, if tries is > 3 then we didn't login so die
        if ($tries > 3) {
            Log::error('Error: could not post successful login within 3 attempts');
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // if we made it here then we've successfully logged in, so tell someone about it
        file_put_contents($response_path.'ironport_dashboard.dump', $response);

        // set url to go to Email
        $url = getenv('IRONPORT_SMA').'/monitor_email/user_report';

        // capture response and dump to file
        $response = $crawler->get($url);
        file_put_contents($response_path.'ironport_userreport.dump', $response);

        // try to extract new CSRF token, otherwise die
        $regex = "/CSRFKey = '(.+)'/";
        if (preg_match($regex, $response, $hits)) {
            $csrftoken = $hits[1];
        } else {
            Log::error('Error: could not get CSRF token');
            die('Error: could not get CSRF Token'.PHP_EOL);
        }

        Log::info('logged in and starting incoming email scrape');

        // set incoming email download url and post data
        $url = getenv('IRONPORT_SMA').'/monitor_email/security_reports/outbreak_filters';

        $post = [
            'date_range'        => 'current_year',
            'CSRFKey'           => $csrftoken,
            'report_def_id'     => 'mga_virus_outbreaks',
            'report_query_id'   => 'mga_virus_outbreaks_threat_details',
            'format'            => 'csv',
        ];

        // capture reponse and dump to file
        $response = $crawler->post($url, $url, $this->postArrayToString($post));
        file_put_contents(storage_path('app/responses/ironport_threats.csv'), $response);

        // Arrays we'll use later
        $keys = [];
        $ironport_threats = [];

        // Do it
        $data = $this->csvToArray(storage_path('app/responses/ironport_threats.csv'), ',');

        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($data) - 1;
        Log::info('read '.$count.' IronPort threats');

        //Use first row for names
        $labels = array_shift($data);

        foreach ($labels as $label) {
            $keys[] = $label;
        }

        Log::info('building associative array...');

        // Bring it all together
        for ($j = 0; $j < $count; $j++) {
            $d = array_combine($keys, $data[$j]);
            $ironport_threats[$j] = $d;
        }

        $email_threats = [];

        foreach ($ironport_threats as $threat) {
            $begin_date_pieces = explode(' ', $threat['Begin Date']);
            $begin_date = $begin_date_pieces[0].'T'.$begin_date_pieces[1];

            $end_date_pieces = explode(' ', $threat['End Date']);
            $end_date = $end_date_pieces[0].'T'.$end_date_pieces[1];

            $email_threats[] = [
                'begin_date'        => $begin_date,
                'end_date'          => $end_date,
                'category'          => $threat['Category'],
                'threat_name'       => $threat['Threat Name'],
                'total_messages'    => intval($threat['Total Messages']),
                'begin_timestamp'   => $threat['Begin Timestamp'],
                'end_timestamp'     => $threat['End Timestamp'],
                'description'       => $threat['Description'],
            ];
        }

        // JSON encode data and dump to file
        file_put_contents(storage_path('app/collections/ironport_threats.json'), \Metaclassing\Utility::encodeJson($email_threats));

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($email_threats as $threat) {
            $es_id = $threat['begin_date'];
            $url = 'http://10.243.32.36:9200/ironport_threats/ironport_threats/'.$es_id;
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $threat,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('IronPort threat was successfully inserted into ES: '.$threat['begin_date']);
            } else {
                Log::error('Something went wrong inserting IronPort threat: '.$threat['begin_date']);
                die('Something went wrong inserting IronPort threat: '.$threat['begin_date'].PHP_EOL);
            }
        }

        /*
         * [2] Process IronPort threats into database
         */

        /*
        Log::info(PHP_EOL.'*****************************************'.PHP_EOL.'* Starting IronPort threats processing! *'.PHP_EOL.'*****************************************');

        foreach ($ironport_threats as $threat) {
            $begindate = rtrim($threat['Begin Date'], ' GMT');
            $enddate = rtrim($threat['End Date'], ' GMT');

            $exists = IronPortThreat::where('begin_date', $begindate)->value('id');

            if ($exists) {
                // update threat record
                $existing_threat = IronPortThreat::find($exists);
                $existing_threat->update([
                    'category'        => $threat['Category'],
                    'threat_type'     => $threat['Threat Name'],
                    'total_messages'  => $threat['Total Messages'],
                    'data'            => \Metaclassing\Utility::encodeJson($threat),
                ]);

                $existing_threat->save();

                // touch threat record to update the 'updated_at' timestamp in case nothing was changed
                $existing_threat->touch();

                Log::info('updated IronPort threat record for '.$threat['Threat Name'].' during '.$begindate);
            } else {
                // create new threat record
                Log::info('creating IronPort threat record for '.$threat['Threat Name'].' during '.$begindate);

                $new_threat = new IronPortThreat();

                $new_threat->begin_date = $begindate;
                $new_threat->end_date = $enddate;
                $new_threat->category = $threat['Category'];
                $new_threat->threat_type = $threat['Threat Name'];
                $new_threat->total_messages = $threat['Total Messages'];
                $new_threat->data = \Metaclassing\Utility::encodeJson($threat);

                $new_threat->save();
            }
        }
        */

        Log::info('* Completed IronPort threats! *');
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

        $poststring = implode('&', $postarray);

        return $poststring;
    }

    /**
     * Function to convert CSV into assoc array.
     *
     * @return array
     */
    public function csvToArray($file, $delimiter)
    {
        if (($handle = fopen($file, 'r')) !== false) {
            $i = 0;

            while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== false) {
                for ($j = 0; $j < count($lineArray); $j++) {
                    $arr[$i][$j] = $lineArray[$j];
                }
                $i++;
            }

            fclose($handle);
        }

        return $arr;
    }
}
