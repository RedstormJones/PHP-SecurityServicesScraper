<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

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

            $email_id = $begin_date.'_'.$sender;

            $incoming_emails_final[] = [
                'email_id'                                  => $email_id,
                'social'                                    => $email['Social'],
                'total_attempted'                           => $email['Total Attempted'],
                'total_threat'                              => $email['Total Threat'],
                'end_date'                                  => $end_date,
                'begin_date'                                => $begin_date,
                'stopped_by_reputation_filtering'           => $email['Stopped by Reputation Filtering'],
                'stopped_as_invalid_recipients'             => $email['Stopped as Invalid Recipients'],
                'spam_detected'                             => $email['Spam Detected'],
                'bulk'                                      => $email['Bulk'],
                'stopped_by_DMARC'                          => $email['Stopped by DMARC'],
                'sender_domain'                             => $sender,
                'begin_timestamp'                           => $email['Begin Timestamp'],
                'end_timestamp'                             => $email['End Timestamp'],
                'stopped_by_content_filter'                 => $email['Stopped by Content Filter'],
                'connections_accepted'                      => $email['Connections Accepted'],
                'connections_rejected'                      => $email['Connections Rejected'],
                'marketing'                                 => $email['Marketing'],
                'stopped_by_recipient_throttling'           => $email['Stopped by Recipient Throttling'],
                'total_graymails'                           => $email['Total Graymails'],
                'clean'                                     => $email['Clean'],
                'detected_by_advanced_malware_protection'   => $email['Detected by Advanced Malware Protection'],
                'orig_value'                                => $email['orig_value'],
                'virus_detected'                            => $email['Virus Detected'],
            ];
        }

        // JSON encode data and dump to file
        file_put_contents(storage_path('app/collections/incoming_email.json'), json_encode($incoming_emails_final));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($incoming_emails_final as $email) {
            // add upsert datetime
            $email['upsert_date'] = Carbon::now()->toAtomString();

            $result = $producer->send([
                [
                    'topic' => 'ironport_incoming_email',
                    'value' => \Metaclassing\Utility::encodeJson($email),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$email['sender_domain']);
            }
        }

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
}
