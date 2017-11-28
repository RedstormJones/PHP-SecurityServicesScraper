<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\IronPort\IncomingEmail;
use Carbon\Carbon;
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
        $url = getenv('IRONPORT_SMA');

        // hit webpage and try to capture CSRF token, otherwise die
        $response = $crawler->get($url);

        // set regex string to dashboard page <title> element
        $regex = '/(<title>.*Centralized Services &gt; System Status <\/title>)/';
        $tries = 0;

        // while NOT at the dashboard page
        while (!preg_match($regex, $response, $hits) && $tries <= 3) {
            // set login url and post data
            $url = getenv('IRONPORT_SMA').'/login';

            $post = [
                'action'    => 'Login',
                'referrer'  => '',
                'screen'    => 'login',
                'username'  => $username,
                'password'  => $password,
            ];

            // try to login
            $response = $crawler->post($url, $url, $this->postArrayToString($post));

            // increment tries and set regex back to dashboard <title>
            $tries++;
            $regex = '/(<title>.*Centralized Services &gt; System Status <\/title>)/';
        }
        // once out of the login loop, if tries is > 3 then we didn't login so die
        if ($tries > 3) {
            Log::error('Error: could not post successful login within 3 attempts');
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // dump response to file
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
            die('Error: could not get CSRF token'.PHP_EOL);
        }

        // set incoming email download url and post data
        $url = getenv('IRONPORT_SMA').'/monitor_email/mail_reports/incoming_mail';

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
        $incoming_emails = [];

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
            $incoming_emails[$j] = $d;
        }

        $incoming_emails_final = [];

        foreach ($incoming_emails as $email) {
            if (preg_match('/\s/', $email['Sender Domain'])) {
                $sender = str_replace(' ', '_', $email['Sender Domain']);
            } elseif (preg_match('/http:\/\//', $email['Sender Domain']) || preg_match('/https:\/\//', $email['Sender Domain'])) {
                $sender_pieces = explode('/', $email['Sender Domain']);
                $sender = $sender_pieces[2];
            } else {
                $sender = $email['Sender Domain'];
            }

            $begin_date_pieces = explode(' ', rtrim($email['Begin Date'], ' GMT'));
            $begin_date = $begin_date_pieces[0].'T'.$begin_date_pieces[1];

            $end_date_pieces = explode(' ', rtrim($email['End Date'], ' GMT'));
            $end_date = $end_date_pieces[0].'T'.$end_date_pieces[1];

            $incoming_emails_final[] = [
                'Social'                                => $email['Social'],
                'TotalAttempted'                        => $email['Total Attempted'],
                'TotalThreat'                           => $email['Total Threat'],
                'EndDate'                               => $end_date,
                'BeginDate'                             => $begin_date,
                'StoppedByReputationFiltering'          => $email['Stopped by Reputation Filtering'],
                'StoppedAsInvalidRecipients'            => $email['Stopped as Invalid Recipients'],
                'SpamDetected'                          => $email['Spam Detected'],
                'Bulk'                                  => $email['Bulk'],
                'StoppedByDMARC'                        => $email['Stopped by DMARC'],
                'SenderDomain'                          => $sender,
                'BeginTimestamp'                        => $email['Begin Timestamp'],
                'EndTimestamp'                          => $email['End Timestamp'],
                'StoppedByContentFilter'                => $email['Stopped by Content Filter'],
                'ConnectionsAccepted'                   => $email['Connections Accepted'],
                'ConnectionsRejected'                   => $email['Connections Rejected'],
                'Marketing'                             => $email['Marketing'],
                'StoppedByRecipientThrottling'          => $email['Stopped by Recipient Throttling'],
                'TotalGraymails'                        => $email['Total Graymails'],
                'Clean'                                 => $email['Clean'],
                'DetectedByAdvancedMalwareProtection'   => $email['Detected by Advanced Malware Protection'],
                'orig_value'                            => $email['orig_value'],
                'VirusDetected'                         => $email['Virus Detected'],
            ];
        }

        // JSON encode data and dump to file
        file_put_contents(storage_path('app/collections/incoming_email.json'), json_encode($incoming_emails_final));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($incoming_emails_final as $email) {
            $result = $producer->send([
                [
                    'topic' => 'ironport_incoming_email',
                    'value' => \Metaclassing\Utility::encodeJson($email),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$email['SenderDomain']);
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

        foreach ($incoming_emails as $email) {
            if (preg_match('/\s/', $email['Sender Domain'])) {
                $sender = str_replace(' ', '_', $email['Sender Domain']);
            } elseif (preg_match('/http:\/\//', $email['Sender Domain']) || preg_match('/https:\/\//', $email['Sender Domain'])) {
                $sender_pieces = explode('/', $email['Sender Domain']);
                $sender = $sender_pieces[2];
            } else {
                $sender = $email['Sender Domain'];
            }

            $begin_date_pieces = explode(' ', rtrim($email['Begin Date'], ' GMT'));
            $begin_date = $begin_date_pieces[0].'T'.$begin_date_pieces[1];
            $email['Begin Date'] = $begin_date;

            $end_date_pieces = explode(' ', rtrim($email['End Date'], ' GMT'));
            $end_date = $end_date_pieces[0].'T'.$end_date_pieces[1];
            $email['End Date'] = $end_date;

            $url = 'http://10.243.32.36:9200/incoming_emails/incoming_emails/';

            $post = [
                'doc'           => $email,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('Incoming email was successfully inserted into ES: '.$email['Sender Domain']);
            } else {
                Log::error('Something went wrong inserting incoming email: '.$email['Sender Domain']);
                die('Something went wrong inserting incoming email: '.$email['Sender Domain'].PHP_EOL);
            }
        }
        */

        /********************************************
         * [2] Process incoming email into database *
         ********************************************/
        /*
        Log::info(PHP_EOL.'***************************************'.PHP_EOL.'* Starting incoming email processing! *'.PHP_EOL.'***************************************');

        Log::info('creating '.$count.' new email records...');

        foreach ($incoming_emails as $email) {
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
        */

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
        $delete_date = Carbon::now()->subMonths(3)->toDateTimeString();

        // get collection of incoming email models that are older than 90 days
        $incomingemails = IncomingEmail::where('updated_at', '<', $delete_date)->get();

        Log::info('deleting '.count($incomingemails).' stale email records...');

        // cycle through the models in the returned collection and soft delete them
        foreach ($incomingemails as $email) {
            $email->delete();
        }
    }
}
