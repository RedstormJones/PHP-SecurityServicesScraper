<?php

namespace App\Console\Commands;

require_once app_path('Console/Crawler/Crawler.php');

use App\PhishMe\AttachmentScenario;
use App\PhishMe\ClickOnlyScenario;
use App\PhishMe\DataEntryScenario;
use App\PhishMe\PhishMeScenario;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetPhishMeScenarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:phishmescenarios';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new PhishMe scenarios';

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
         * [1] Get PhishMe scenarios
         */

        Log::info(PHP_EOL.PHP_EOL.'***************************************'.PHP_EOL.'* Starting PhishMe scenarios crawler! *'.PHP_EOL.'***************************************');

        // setup tries, cookiejar, and response path variables
        $tries = 0;
        $cookiejar = storage_path('app/cookies/phishme_cookie.txt');
        $response_path = storage_path('app/responses/');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // setup HTTP headers with token
        $token = getenv('PHISHME_TOKEN');
        $headers = [
            'Authorization: Token token='.$token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // point url to scenarios
        $url = 'https:/'.'/login.phishme.com/api/v1/scenarios.json';

        // capture response and dump to file
        $jsonresponse = $crawler->get($url);
        file_put_contents($response_path.'phishme_scenarios.json', $jsonresponse);

        // if response contains Access denied or 403 Forbidden and tries is less than 100 then keep trying
        while (preg_match('/.+(Access denied.)|(403 Forbidden).+/', $jsonresponse, $hits) && $tries < 100) {
            $jsonresponse = $crawler->get($url);
            file_put_contents($response_path.'phishme_scenarios.json', $jsonresponse);

            $tries++;
        }
        // if we exit the loop and tries is at 100 then bail
        if ($tries == 100) {
            Log::error('Error: could not get list of PhishMe scenarios');
            die('Error: Could not get list of PhishMe scenarios');
        }

        // otherwise we should have a good list of scenarios now
        $response = \Metaclassing\Utility::decodeJson($jsonresponse);

        // instantiate collection array
        $collection = [];

        // cycle through scenarios and download full csv for each
        foreach ($response as $scenario) {
            // grab scenario id and type from each scenario
            $scenario_id = $scenario['id'];
            $scenario_type = str_replace(' ', '', $scenario['scenario_type']);
            $scenario_title = $scenario['title'];

            // piont url to scenario full csv download
            $url = 'https:/'.'/login.phishme.com/api/v1/scenario/'.$scenario_id.'/full_csv';

            // capture response and dump to file
            $response = $crawler->get($url);
            file_put_contents($response_path.'phishme_scenario_'.$scenario_id.'.csv', $response);

            // if we get access denied or 403 forbidden then wait 3 seconds and try again
            while (preg_match('/.+(Access denied.)|(403 Forbidden).+|(API Token Busy:).+/', $response, $hits)) {
                Log::notice('Access denied: sleeping for 3 seconds before trying again...');
                sleep(3);

                $response = $crawler->get($url);
                file_put_contents($response_path.'phishme_scenario_'.$scenario_id.'.csv', $response);
            }

            // instantiate keys and newArray arrays
            $keys = [];
            $newArray = [];

            // convert csv data to workable array
            $data = $this->csvToArray(storage_path('app/responses/phishme_scenario_'.$scenario_id.'.csv'), ',');

            // Set number of elements (minus 1 because we shift off the first row)
            $count = count($data) - 1;

            // create keys using first row
            $labels = array_shift($data);

            foreach ($labels as $label) {
                $keys[] = $label;
            }

            // add keys for scenario type, id and title
            $keys[] = 'scenario_type';
            $keys[] = 'scenario_id';
            $keys[] = 'scenario_title';

            // Bring it all together
            for ($j = 0; $j < $count; $j++) {
                array_push($data[$j], 'App\PhishMe\\'.$scenario_type.'Scenario');
                array_push($data[$j], $scenario_id);
                array_push($data[$j], $scenario_title);

                $d = array_combine($keys, $data[$j]);
                $newArray[$j] = $d;
            }

            // cycle through newArray and build collectoin
            foreach ($newArray as $scenario) {
                // this creates a unique scenario id for each element of each scenario
                $scenario['scenario_id'] = $scenario_id.':'.$scenario['Recipient Name'];
                $scenario['scenario_title'] = $scenario_title;
                $collection[] = $scenario;
            }
        }

        // dump collection to file
        file_put_contents(storage_path('app/collections/scenario_collection.json'), json_encode($collection));

        /*
         * [2] Process PhishMe scenarios into database
         */

        Log::info(PHP_EOL.'******************************************'.PHP_EOL.'* Starting PhishMe scenarios processing! *'.PHP_EOL.'******************************************');

        // cycle through each scenario result
        foreach ($collection as $result) {
            // convert last email status timestamp to acceptable format
            $lastemaildate = Carbon::createFromFormat('d/m/Y H:i:s', $result['Last Email Status Timestamp'])->toDateTimeString();

            // handle reported phish timestamp values of ''
            if ($result['Reported Phish Timestamp'] == '') {
                $reportedphish_timestamp = null;
            } else {
                // convert reported phish timestamp to acceptable format
                $reportedphish_timestamp = Carbon::createFromFormat('d/m/Y H:i:s', $result['Reported Phish Timestamp'])->toDateTimeString();
            }

            // handle time to report values of ''
            if ($result['Time to Report (in seconds)'] == '') {
                $timetoreport = 0;
            } else {
                $timetoreport = $result['Time to Report (in seconds)'];
            }

            // create scenario records based off scenario type
            switch ($result['scenario_type']) {
                case 'App\PhishMe\AttachmentScenario':
                    // handle viewed education timestamp values of ''
                    if ($result['Viewed Education Timestamp'] == '') {
                        $viewed_education_timestamp = null;
                    } else {
                        // convert viewed education timestamp to acceptable format
                        $viewed_education_timestamp = Carbon::createFromFormat('d/m/Y H:i:s', $result['Viewed Education Timestamp'])->toDateTimeString();
                    }

                    $exists = AttachmentScenario::where('scenario_id', $result['scenario_id'])->value('id');

                    if (!$exists) {
                        Log::info('creating new attachment scenario for '.$result['scenario_title']);

                        $attachment = new AttachmentScenario();

                        $attachment->scenario_id = $result['scenario_id'];
                        $attachment->scenario_type = $result['scenario_type'];
                        $attachment->scenario_title = $result['scenario_title'];
                        $attachment->email = $result['Email'];
                        $attachment->recipient_name = $result['Recipient Name'];
                        $attachment->recipient_group = $result['Recipient Group'];
                        $attachment->department = $result['Department'];
                        $attachment->location = $result['Location'];
                        $attachment->viewed_education = $result['Viewed Education?'];
                        $attachment->viewed_education_timestamp = $viewed_education_timestamp;
                        $attachment->reported_phish = $result['Reported Phish?'];
                        $attachment->new_repeat_reporter = $result['New/Repeat Reporter'];
                        $attachment->reported_phish_timestamp = $reportedphish_timestamp;
                        $attachment->time_to_report = $timetoreport;
                        $attachment->remote_ip = $result['Remote IP'];
                        $attachment->geoip_country = $result['GeoIP Country'];
                        $attachment->geoip_city = $result['GeoIP City'];
                        $attachment->geoip_organization = $result['GeoIP Organization'];
                        $attachment->last_dsn = $result['Last DSN'];
                        $attachment->last_email_status = $result['Last Email Status'];
                        $attachment->last_email_status_timestamp = $lastemaildate;
                        $attachment->language = $result['Language'];
                        $attachment->browser = $result['Browser'];
                        $attachment->user_agent = $result['User-Agent'];
                        $attachment->mobile = $result['Mobile?'];
                        $attachment->data = \Metaclassing\Utility::encodeJson($result);

                        $attachment->save();

                        // create new scenario model
                        $scenario = new PhishMeScenario();
                        $scenario->scenario_title = $result['scenario_title'];
                        $scenario->reportable_id = $result['scenario_id'];
                        $scenario->reportable_type = $result['scenario_type'];
                        $scenario->data = \Metaclassing\Utility::encodeJson($result);

                        $attachment->reports()->save($scenario);
                    } else {
                        Log::info('scenario already exists: '.$result['scenario_title'].' : '.$result['scenario_id']);
                    }

                    break;

                case 'App\PhishMe\ClickOnlyScenario':
                    // handle clicked link timestamp values of ''
                    if ($result['Clicked Link Timestamp'] == '') {
                        $clickedlink_timestamp = null;
                    } else {
                        // convert clicked link timestamp to acceptable format
                        $clickedlink_timestamp = Carbon::createFromFormat('d/m/Y H:i:s', $result['Clicked Link Timestamp'])->toDateTimeString();
                    }

                    // handle seconds spent on education page values of ''
                    if ($result['Seconds Spent on Education Page'] == '') {
                        $education_seconds = 0;
                    } else {
                        $education_seconds = $result['Seconds Spent on Education Page'];
                    }

                    $exists = ClickOnlyScenario::where('scenario_id', $result['scenario_id'])->value('id');

                    if (!$exists) {
                        Log::info('creating new click only scenario for '.$result['scenario_title']);

                        $clickonly = new ClickOnlyScenario();

                        $clickonly->scenario_id = $result['scenario_id'];
                        $clickonly->scenario_type = $result['scenario_type'];
                        $clickonly->scenario_title = $result['scenario_title'];
                        $clickonly->email = $result['Email'];
                        $clickonly->recipient_name = $result['Recipient Name'];
                        $clickonly->recipient_group = $result['Recipient Group'];
                        $clickonly->department = $result['Department'];
                        $clickonly->location = $result['Location'];
                        $clickonly->clicked_link = $result['Clicked Link?'];
                        $clickonly->clicked_link_timestamp = $clickedlink_timestamp;
                        $clickonly->reported_phish = $result['Reported Phish?'];
                        $clickonly->new_repeat_reporter = $result['New/Repeat Reporter'];
                        $clickonly->reported_phish_timestamp = $reportedphish_timestamp;
                        $clickonly->time_to_report = $timetoreport;
                        $clickonly->remote_ip = $result['Remote IP'];
                        $clickonly->geoip_country = $result['GeoIP Country'];
                        $clickonly->geoip_city = $result['GeoIP City'];
                        $clickonly->geoip_organization = $result['GeoIP Organization'];
                        $clickonly->last_dsn = $result['Last DSN'];
                        $clickonly->last_email_status = $result['Last Email Status'];
                        $clickonly->last_email_status_timestamp = $lastemaildate;
                        $clickonly->language = $result['Language'];
                        $clickonly->browser = $result['Browser'];
                        $clickonly->user_agent = $result['User-Agent'];
                        $clickonly->mobile = $result['Mobile?'];
                        $clickonly->seconds_spent_on_education = $education_seconds;
                        $clickonly->data = \Metaclassing\Utility::encodeJson($result);

                        $clickonly->save();

                        // create new scenario model
                        $scenario = new PhishMeScenario();
                        $scenario->scenario_title = $result['scenario_title'];
                        $scenario->reportable_id = $result['scenario_id'];
                        $scenario->reportable_type = $result['scenario_type'];
                        $scenario->data = \Metaclassing\Utility::encodeJson($result);

                        $clickonly->reports()->save($scenario);
                    } else {
                        Log::info('scenario already exists: '.$result['scenario_title'].' : '.$result['scenario_id']);
                    }

                    break;

                case 'App\PhishMe\DataEntryScenario':
                    // handle clicked link timestamp values of ''
                    if ($result['Clicked Link Timestamp'] == '') {
                        $clickedlink_timestamp = null;
                    } else {
                        // convert clicked link timestamp to acceptable format
                        $clickedlink_timestamp = Carbon::createFromFormat('d/m/Y H:i:s', $result['Clicked Link Timestamp'])->toDateTimeString();
                    }

                    // handle submitted form timestamp values of ''
                    if ($result['Submitted Form Timestamp'] == '') {
                        $submittedform_timestamp = null;
                    } else {
                        // convert submitted form timestamp to acceptable format
                        $submittedform_timestamp = Carbon::createFromFormat('d/m/Y H:i:s', $result['Submitted Form Timestamp'])->toDateTimeString();
                    }

                    // handle seconds spent on education page values of ''
                    if ($result['Seconds Spent on Education Page'] == '') {
                        $education_seconds = 0;
                    } else {
                        $education_seconds = $result['Seconds Spent on Education Page'];
                    }

                    $exists = DataEntryScenario::where('scenario_id', $result['scenario_id'])->value('id');

                    if (!$exists) {
                        Log::info('creating new data entry scenario for '.$result['scenario_title']);

                        $dataentry = new DataEntryScenario();

                        $dataentry->scenario_id = $result['scenario_id'];
                        $dataentry->scenario_type = $result['scenario_type'];
                        $dataentry->scenario_title = $result['scenario_title'];
                        $dataentry->email = $result['Email'];
                        $dataentry->recipient_name = $result['Recipient Name'];
                        $dataentry->recipient_group = $result['Recipient Group'];
                        $dataentry->department = $result['Department'];
                        $dataentry->location = $result['Location'];
                        $dataentry->clicked_link = $result['Clicked Link?'];
                        $dataentry->clicked_link_timestamp = $clickedlink_timestamp;
                        $dataentry->submitted_form = $result['Submitted Form'];
                        $dataentry->submitted_form_timestamp = $submittedform_timestamp;
                        $dataentry->submitted_data = $result['Submitted Data'];
                        $dataentry->phished_username = $result['Username'];
                        $dataentry->entered_password = $result['Entered Password?'];
                        $dataentry->reported_phish = $result['Reported Phish?'];
                        $dataentry->new_repeat_reporter = $result['New/Repeat Reporter'];
                        $dataentry->reported_phish_timestamp = $reportedphish_timestamp;
                        $dataentry->time_to_report = $timetoreport;
                        $dataentry->remote_ip = $result['Remote IP'];
                        $dataentry->geoip_country = $result['GeoIP Country'];
                        $dataentry->geoip_city = $result['GeoIP City'];
                        $dataentry->geoip_organization = $result['GeoIP Organization'];
                        $dataentry->last_dsn = $result['Last DSN'];
                        $dataentry->last_email_status = $result['Last Email Status'];
                        $dataentry->last_email_status_timestamp = $lastemaildate;
                        $dataentry->language = $result['Language'];
                        $dataentry->browser = $result['Browser'];
                        $dataentry->user_agent = $result['User-Agent'];
                        $dataentry->mobile = $result['Mobile?'];
                        $dataentry->seconds_spent_on_education = $education_seconds;
                        $dataentry->data = \Metaclassing\Utility::encodeJson($result);

                        $dataentry->save();

                        // create new scenario model
                        $scenario = new PhishMeScenario();
                        $scenario->scenario_title = $result['scenario_title'];
                        $scenario->reportable_id = $result['scenario_id'];
                        $scenario->reportable_type = $result['scenario_type'];
                        $scenario->data = \Metaclassing\Utility::encodeJson($result);

                        $dataentry->reports()->save($scenario);
                    } else {
                        Log::info('scenario already exists: '.$result['scenario_title'].' : '.$result['scenario_id']);
                    }

                    break;
            }
        }

        Log::info('* Completed PhishMe scenarios! *');
    }

    /**
     * Function to convert csv data to usable array.
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
