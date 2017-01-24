<?php

namespace App\Console\Commands\Process;

use App\PhishMe\AttachmentScenario;
use App\PhishMe\ClickOnlyScenario;
use App\PhishMe\DataEntryScenario;
use App\PhishMe\PhishMeScenario;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        foreach ($scenario_results as $result) {
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
                        $scenario->reportable_id = $result['scenario_id'];
                        $scenario->reportable_type = $result['scenario_type'];
                        $scenario->data = \Metaclassing\Utility::encodeJson($result);

                        $attachment->reports()->save($scenario);
                    } else {
                        $scenario = AttachmentScenario::find($exists);

                        $scenario->update([
                            'scenario_id'                   => $result['scenario_id'],
                            'scenario_type'                 => $result['scenario_type'],
                            'scenario_title'                => $result['scenario_title'],
                            'email'                         => $result['Email'],
                            'recipient_name'                => $result['Recipient Name'],
                            'recipient_group'               => $result['Recipient Group'],
                            'department'                    => $result['Department'],
                            'location'                      => $result['Location'],
                            'viewed_education'              => $result['Viewed Education?'],
                            'viewed_education_timestamp'    => $viewed_education_timestamp,
                            'reported_phish'                => $result['Reported Phish?'],
                            'new_repeat_reporter'           => $result['New/Repeat Reporter'],
                            'reported_phish_timestamp'      => $reportedphish_timestamp,
                            'time_to_report'                => $timetoreport,
                            'remote_ip'                     => $result['Remote IP'],
                            'geoip_country'                 => $result['GeoIP Country'],
                            'geoip_city'                    => $result['GeoIP City'],
                            'geoip_organization'            => $result['GeoIP Organization'],
                            'last_dsn'                      => $result['Last DSN'],
                            'last_email_status'             => $result['Last Email Status'],
                            'last_email_status_timestamp'   => $lastemaildate,
                            'language'                      => $result['Language'],
                            'browser'                       => $result['Browser'],
                            'user_agent'                    => $result['User-Agent'],
                            'mobile'                        => $result['Mobile?'],
                            'data'                          => \Metaclassing\Utility::encodeJson($result),
                        ]);

                        $scenario->save();

                        // touch scenario model to update the 'updated_at' timestamp in case nothing was changed
                        $scenario->touch();

                        Log::info('updated '.$result['scenario_title'].' : '.$result['scenario_id']);
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
                        $scenario->reportable_id = $result['scenario_id'];
                        $scenario->reportable_type = $result['scenario_type'];
                        $scenario->data = \Metaclassing\Utility::encodeJson($result);

                        $clickonly->reports()->save($scenario);
                    } else {
                        $scenario = ClickOnlyScenario::find($exists);

                        $scenario->update([
                            'scenario_id'                   => $result['scenario_id'],
                            'scenario_type'                 => $result['scenario_type'],
                            'scenario_title'                => $result['scenario_title'],
                            'email'                         => $result['Email'],
                            'recipient_name'                => $result['Recipient Name'],
                            'recipient_group'               => $result['Recipient Group'],
                            'department'                    => $result['Department'],
                            'location'                      => $result['Location'],
                            'clicked_link'                  => $result['Clicked Link?'],
                            'clicked_link_timestamp'        => $clickedlink_timestamp,
                            'reported_phish'                => $result['Reported Phish?'],
                            'new_repeat_reporter'           => $result['New/Repeat Reporter'],
                            'reported_phish_timestamp'      => $reportedphish_timestamp,
                            'time_to_report'                => $timetoreport,
                            'remote_ip'                     => $result['Remote IP'],
                            'geoip_country'                 => $result['GeoIP Country'],
                            'geoip_city'                    => $result['GeoIP City'],
                            'geoip_organization'            => $result['GeoIP Organization'],
                            'last_dsn'                      => $result['Last DSN'],
                            'last_email_status'             => $result['Last Email Status'],
                            'last_email_status_timestamp'   => $lastemaildate,
                            'language'                      => $result['Language'],
                            'browser'                       => $result['Browser'],
                            'user_agent'                    => $result['User-Agent'],
                            'mobile'                        => $result['Mobile?'],
                            'seconds_spent_on_education'    => $education_seconds,
                            'data'                          => \Metaclassing\Utility::encodeJson($result),
                        ]);

                        $scenario->save();

                        // touch scenario model to update the 'updated_at' timestamp in case nothing was changed
                        $scenario->touch();

                        Log::info('updated '.$result['scenario_title'].' : '.$result['scenario_id']);
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
                        $scenario->reportable_id = $result['scenario_id'];
                        $scenario->reportable_type = $result['scenario_type'];
                        $scenario->data = \Metaclassing\Utility::encodeJson($result);

                        $dataentry->reports()->save($scenario);
                    } else {
                        $scenario = DataEntryScenario::find($exists);

                        $scenario->update([
                            'scenario_id'                   => $result['scenario_id'],
                            'scenario_type'                 => $result['scenario_type'],
                            'scenario_title'                => $result['scenario_title'],
                            'email'                         => $result['Email'],
                            'recipient_name'                => $result['Recipient Name'],
                            'recipient_group'               => $result['Recipient Group'],
                            'department'                    => $result['Department'],
                            'location'                      => $result['Location'],
                            'clicked_link'                  => $result['Clicked Link?'],
                            'clicked_link_timestamp'        => $clickedlink_timestamp,
                            'submitted_form'                => $result['Submitted Form'],
                            'submitted_form_timestamp'      => $submittedform_timestamp,
                            'submitted_data'                => $result['Submitted Data'],
                            'phished_username'              => $result['Username'],
                            'entered_password'              => $result['Entered Password?'],
                            'reported_phish'                => $result['Reported Phish?'],
                            'new_repeat_reporter'           => $result['New/Repeat Reporter'],
                            'reported_phish_timestamp'      => $reportedphish_timestamp,
                            'time_to_report'                => $timetoreport,
                            'remote_ip'                     => $result['Remote IP'],
                            'geoip_country'                 => $result['GeoIP Country'],
                            'geoip_city'                    => $result['GeoIP City'],
                            'geoip_organization'            => $result['GeoIP Organization'],
                            'last_dsn'                      => $result['Last DSN'],
                            'last_email_status'             => $result['Last Email Status'],
                            'last_email_status_timestamp'   => $lastemaildate,
                            'language'                      => $result['Language'],
                            'browser'                       => $result['Browser'],
                            'user_agent'                    => $result['User-Agent'],
                            'mobile'                        => $result['Mobile?'],
                            'seconds_spent_on_education'    => $education_seconds,
                            'data'                          => \Metaclassing\Utility::encodeJson($result),
                        ]);

                        $scenario->save();

                        // touch scenario model to update the 'updated_at' timestamp in case nothing was changed
                        $scenario->touch();

                        Log::info('updated '.$result['scenario_title'].' : '.$result['scenario_id']);
                    }

                    break;

            }
        }

        Log::info('* Completed PhishMe scenarios! *');
    }
}    // end of ProcessPhishMeScenarios command class
