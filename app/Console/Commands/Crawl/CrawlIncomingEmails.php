<?php

namespace App\Console\Commands\Crawl;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlIncomingEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:incomingemail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl IronPort web console and parse out incoming email statistics';

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
        $username = getenv('IRONPORT_USERNAME');
        $password = getenv('IRONPORT_PASSWORD');

        $response_path = storage_path('app/responses/');

        // setup cookiejar file
        $cookiejar = storage_path('app/cookies/ironport_cookie.txt');
        echo 'Storing cookies at '.$cookiejar.PHP_EOL;

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
                echo 'Found CSRF token: '.$csrftoken.PHP_EOL;
            } else {
                die('Error: could not get CSRF token'.PHP_EOL);
            }

            // set login url and post data
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
            die('Error: could not post successful login within 3 attempts'.PHP_EOL);
        }

        // if we made it here then we've successfully logged in, so tell someone about it
        echo 'Logged In'.PHP_EOL;

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
            die('Error: could not get CSRF Token'.PHP_EOL);
        }

        echo 'Starting incoming email scrape'.PHP_EOL;

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
        $newArray = [];

        // Do it
        $data = $this->csvToArray(storage_path('app/responses/incoming_email.csv'), ',');

        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($data) - 1;
        echo 'Read '.$count.' incoming email records'.PHP_EOL;

        //Use first row for names
        $labels = array_shift($data);

        echo 'Creating keys..'.PHP_EOL;
        foreach ($labels as $label) {
            $keys[] = $label;
        }

        // Bring it all together
        echo 'Building associative array..'.PHP_EOL;
        for ($j = 0; $j < $count; $j++) {
            $d = array_combine($keys, $data[$j]);
            $newArray[$j] = $d;
        }

        // JSON encode data and dump to file
        file_put_contents(storage_path('app/collections/incoming_email.json'), json_encode($newArray));
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
