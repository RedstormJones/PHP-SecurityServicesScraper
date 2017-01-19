<?php

namespace App\Console\Commands;

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
        $url = 'https:/'.'/dh1146-sma1.iphmx.com';

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
            $url = 'https:/'.'/dh1146-sma1.iphmx.com/login';

            $post = [
                'action'    => 'Login',
                'referrer'  => 'https:/'.'/dh1146-sma1.iphmx.com/default',
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
        $url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email/user_report';

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
        $url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email/security_reports/outbreak_filters';

        $post = [
            'date_range'        => 'current_year',
            'CSRFKey'           => $csrftoken,
            'report_def_id'     => 'mga_virus_outbreaks',
            'report_query_id'   => 'mga_virus_outbreaks_threat_details',
            'format'            => 'csv',
        ];

        // capture reponse and dump to file
        $response = $crawler->post($url, $url, $this->postArrayToString($post));
        file_put_contents(storage_path('app/responses/threat_details.csv'), $response);

        // Arrays we'll use later
        $keys = [];
        $newArray = [];

        // Do it
        $data = $this->csvToArray(storage_path('app/responses/threat_details.csv'), ',');

        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($data) - 1;
        echo 'Read '.$count.' incoming email records'.PHP_EOL;
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
            $newArray[$j] = $d;
        }

        // JSON encode data and dump to file
        file_put_contents(storage_path('app/collections/threat_details.json'), \Metaclassing\Utility::encodeJson($newArray));

        /*
         * [2] Process IronPort threats into database
         */

        Log::info(PHP_EOL.'*****************************************'.PHP_EOL.'* Starting IronPort threats processing! *'.PHP_EOL.'*****************************************');

        foreach ($newArray as $threat) {
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
