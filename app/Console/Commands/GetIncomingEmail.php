<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use App\IronPort\IncomingEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetIncomingEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:incomingemail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new incoming email';

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
        /******************************
         * [1] Get all incoming email *
         ******************************/

        Log::info(PHP_EOL.PHP_EOL.'************************************'.PHP_EOL.'* Starting incoming email crawler! *'.PHP_EOL.'************************************');

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

            // set login url and post data
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

        // dump response to file
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
            die('Error: could not get CSRF token'.PHP_EOL);
        }

        // set incoming email download url and post data
        $url = 'https:/'.'/dh1146-sma1.iphmx.com/monitor_email/mail_reports/incoming_mail';

        $post = [
            'profile_type'      => 'domain',
            'format'            => 'csv',
            'CSRFKey'           => $csrftoken,
            'report_query_id'   => 'sma_incoming_mail_domain_search',
            'date_range'        => 'current_day',
            'report_def_id'     => 'sma_incoming_mail',
        ];

        // capture reponse and dump to file
        $response = $crawler->post($url, $url, $this->postArrayToString($post));
        file_put_contents(storage_path('app/responses/incoming_email.csv'), $response);

        // Arrays we'll use later
        $keys = [];
        $newArray = [];

        // Do it
        $data = $this->csvToArray(storage_path('app/responses/incoming_email.csv'), ',');

        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($data) - 1;
        Log::info('read '.$count.' new incoming emails');

        //Use first row for names
        $labels = array_shift($data);

        foreach ($labels as $label) {
            $keys[] = $label;
        }

        // Bring it all together
        Log::info('building associative array...');
        for ($j = 0; $j < $count; $j++) {
            $d = array_combine($keys, $data[$j]);
            $newArray[$j] = $d;
        }

        // JSON encode data and dump to file
        file_put_contents(storage_path('app/collections/incoming_email.json'), json_encode($newArray));

        /********************************************
         * [2] Process incoming email into database *
         ********************************************/

        Log::info(PHP_EOL.'***************************************'.PHP_EOL.'* Starting incoming email processing! *'.PHP_EOL.'***************************************');

        Log::info('creating '.$count.' new email records...');

        foreach ($newArray as $email) {
            $begindate = rtrim($email['Begin Date'], ' GMT');
            $enddate = rtrim($email['End Date'], ' GMT');

            $new_email = new IncomingEmail();

            $new_email->begin_date = $begindate;
            $new_email->end_date = $enddate;
            $new_email->sender_domain = $email['Sender Domain'];
            $new_email->connections_rejected = $email['Connections Rejected'];
            $new_email->connections_accepted = $email['Connections Accepted'];
            $new_email->total_attempted = $email['Total Attempted'];
            $new_email->stopped_by_recipient_throttling = $email['Stopped by Recipient Throttling'];
            $new_email->stopped_by_reputation_filtering = $email['Stopped by Reputation Filtering'];
            $new_email->stopped_by_content_filter = $email['Stopped by Content Filter'];
            $new_email->stopped_as_invalid_recipients = $email['Stopped as Invalid Recipients'];
            $new_email->spam_detected = $email['Spam Detected'];
            $new_email->virus_detected = $email['Virus Detected'];
            $new_email->amp_detected = $email['Detected by Advanced Malware Protection'];
            $new_email->total_threats = $email['Total Threat'];
            $new_email->marketing = $email['Marketing'];
            $new_email->social = $email['Social'];
            $new_email->bulk = $email['Bulk'];
            $new_email->total_graymails = $email['Total Graymails'];
            $new_email->clean = $email['Clean'];
            $new_email->data = json_encode($email);

            $new_email->save();
        }

        $this->processDeletes();

        Log::info('* Incoming email completed! *'.PHP_EOL);
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
     * Function to convert CSV into associative array.
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

    /**
     * Function to process softdeletes for incoming email.
     *
     * @return void
     */
    public function processDeletes()
    {
        $today = new \DateTime('now');
        $yesterday = $today->modify('-3 months');
        $delete_date = $yesterday->format('Y-m-d H:i:s');

        // get collection of incoming email models that are older than 90 days
        $incomingemails = IncomingEmail::where('updated_at', '<', $delete_date)->get();

        Log::info('deleting stale email records...');

        // cycle through the models in the returned collection and soft delete them
        foreach ($incomingemails as $email) {
            $email->delete();
        }
    }
}
