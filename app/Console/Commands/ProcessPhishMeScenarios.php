<?php

namespace App\Console\Commands;

use App\PhishMe\PhishMeScenario;
use App\PhishMe\AttachmentScenario;
use App\PhishMe\ClickOnlyScenario;
use App\PhishMe\DataEntryScenario;
use Illuminate\Console\Command;

class ProcessPhishMeScenarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:phishmescenarios';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new PhishMe scenario data and update model';

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
		// get scenario data and JSON decode it
		$contents = file_get_contents(storage_path('app/collections/scenario_collection.json'));
		$scenario_results = \Metaclassing\Utility::decodeJson($contents);

		// cycle through each scenario result
		foreach($scenario_results as $result)
		{
			// convert last email status timestamp to acceptable format
			$lastemailtimestamp = strtotime($result['Last Email Status Timestamp']);
			$date = new \DateTime('@'.$lastemailtimestamp);
			$lastemaildate = $date->format('Y-m-d H:i:s');

			// handle reported phish timestamp values of ''
			if($result['Reported Phish Timestamp'] == '')
			{
				$reportedphish_timestamp = NULL;
			}
			else
			{
				// convert reported phish timestamp to acceptable format
				$reportedphish_timestamp = strtotime($result['Reported Phish Timestamp']);
				$date = new \DateTime('@'.$reportedphish_timestamp);
				$reportedphish_timestamp = $date->format('Y-m-d H:i:s');
			}

			// handle time to report values of ''
			if($result['Time to Report (in seconds)'] == '')
			{
				$timetoreport = 0;
			}
			else
			{
				$timetoreport = $result['Time to Report (in seconds)'];
			}


			// create scenario records based off scenario type
			switch ($result['scenario_type'])
			{
				case 'App\PhishMe\AttachmentScenario':

					// handle viewed education timestamp values of ''
					if($result['Viewed Education Timestamp'] == '')
					{
						$viewededucation_timestamp = NULL;
					}
					else
					{
						// convert viewed education timestamp to acceptable format
						$viewededucation_timestamp = strtotime($result['Viewed Education Timestamp']);
						$date = new \DateTime('@'.$viewededucation_timestamp);
						$viewededucation_timestamp = $date->format('Y-m-d H:i:s');
					}

					echo 'creating new attachment scenario: '.$result['scenario_id'].PHP_EOL;
					$attachment = new AttachmentScenario();

					$attachment->scenario_id = $result['scenario_id'];
					$attachment->scenario_type = $result['scenario_type'];
					$attachment->email = $result['Email'];
					$attachment->recipient_name = $result['Recipient Name'];
					$attachment->recipient_group = $result['Recipient Group'];
					$attachment->department = $result['Department'];
					$attachment->location = $result['Location'];
					$attachment->viewed_education = $result['Viewed Education?'];
					$attachment->viewed_education_timestamp = $viewededucation_timestamp;
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
					$scenario->reportable_id = $result['scenario_id'];
					$scenario->reportable_type = $result['scenario_type'];
					$scenario->data = \Metaclassing\Utility::encodeJson($result);

					$attachment->reports()->save($scenario);

					break;

				case 'App\PhishMe\ClickOnlyScenario':

					// handle clicked link timestamp values of ''
					if($result['Clicked Link Timestamp'] == '')
					{
						$clickedlink_timestamp = NULL;
					}
					else
					{
						// convert clicked link timestamp to acceptable format
						$clickedlink_timestamp = strtotime($result['Clicked Link Timestamp']);
						$date = new \DateTime('@'.$clickedlink_timestamp);
						$clickedlink_timestamp = $date->format('Y-m-d H:i:s');
					}

					// handle seconds spent on education page values of ''
					if($result['Seconds Spent on Education Page'] == '')
					{
						$education_seconds = 0;
					}
					else
					{
						$education_seconds = $result['Seconds Spent on Education Page'];
					}

					echo 'creating new click only scenario: '.$result['scenario_id'].PHP_EOL;
                    $clickonly = new ClickOnlyScenario();

                    $clickonly->scenario_id = $result['scenario_id'];
                    $clickonly->scenario_type = $result['scenario_type'];
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
					$scenario->reportable_id = $result['scenario_id'];
					$scenario->reportable_type = $result['scenario_type'];
					$scenario->data = \Metaclassing\Utility::encodeJson($result);

                    $clickonly->reports()->save($scenario);

					break;

				case 'App\PhishMe\DataEntryScenario':

					// handle clicked link timestamp values of ''
					if($result['Clicked Link Timestamp'] == '')
					{
						$clickedlink_timestamp = NULL;
					}
					else
					{
						// convert clicked link timestamp to acceptable format
						$clickedlink_timestamp = strtotime($result['Clicked Link Timestamp']);
						$date = new \DateTime('@'.$clickedlink_timestamp);
						$clickedlink_timestamp = $date->format('Y-m-d H:i:s');
					}

					// handle submitted form timestamp values of ''
					if($result['Submitted Form Timestamp'] == '')
					{
						$submittedform_timestamp = NULL;
					}
					else
					{
						// convert submitted form timestamp to acceptable format
						$submittedform_timestamp = strtotime($result['Submitted Form Timestamp']);
						$date = new \DateTime('@'.$submittedform_timestamp);
						$submittedform_timestamp = $date->format('Y-m-d H:i:s');
					}

					// handle seconds spent on education page values of ''
					if($result['Seconds Spent on Education Page'] == '')
					{
						$education_seconds = 0;
					}
					else
					{
						$education_seconds = $result['Seconds Spent on Education Page'];
					}

					echo 'creating new data entry scenario: '.$result['scenario_id'].PHP_EOL;
                    $dataentry = new DataEntryScenario();

                    $dataentry->scenario_id = $result['scenario_id'];
                    $dataentry->scenario_type = $result['scenario_type'];
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
					$scenario->reportable_id = $result['scenario_id'];
					$scenario->reportable_type = $result['scenario_type'];
					$scenario->data = \Metaclassing\Utility::encodeJson($result);

                    $dataentry->reports()->save($scenario);

					break;

			}	// end of switch statement

		}	// end of foreach loop

    }	// end of handle function

}	// end of ProcessPhishMeScenarios command class
