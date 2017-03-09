<?php

namespace App\Http\Controllers;

use App\PhishMe\AttachmentScenario;
use App\PhishMe\ClickOnlyScenario;
use App\PhishMe\DataEntryScenario;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class PhishMeController extends Controller
{
    /**
     * Create new PhishMe Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get aggregate statistics on click test results for a given date (i.e. 2016-AUG).
     *
     * @return \Illuminate\Http\Response
     */
    public function getClickTestResultAggregates($date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $ent_scenarios = [];
            $ktg_scenarios = [];

            // get enterprise scenarios for given date
            $ent_attachments = AttachmentScenario::where('scenario_title', $date.' Enterprise Click Test')->get();
            $ent_clickonlys = ClickOnlyScenario::where('scenario_title', $date.' Enterprise Click Test')->get();
            $ent_dataentrys = DataEntryScenario::where('scenario_title', $date.' Enterprise Click Test')->get();

            // build enterprise scenarios array
            foreach ($ent_attachments as $data) {
                $ent_scenarios[] = $data;
            }
            foreach ($ent_clickonlys as $data) {
                $ent_scenarios[] = $data;
            }
            foreach ($ent_dataentrys as $data) {
                $ent_scenarios[] = $data;
            }

            // get ktg scenarios for given date
            $ktg_attachments = AttachmentScenario::where('scenario_title', 'like', $date.' KTG Click Test%')->get();
            $ktg_clickonlys = ClickOnlyScenario::where('scenario_title', 'like', $date.' KTG Click Test%')->get();
            $ktg_dataentrys = DataEntryScenario::where('scenario_title', 'like', $date.' KTG Click Test%')->get();

            // build ktg scenarios array
            foreach ($ktg_attachments as $data) {
                $ktg_scenarios[] = $data;
            }
            foreach ($ktg_clickonlys as $data) {
                $ktg_scenarios[] = $data;
            }
            foreach ($ktg_dataentrys as $data) {
                $ktg_scenarios[] = $data;
            }

            // calculate aggregates
            $ent_aggregates = [];
            $ktg_aggregates = [];

            // enterprise aggregates
            $ent_aggregates['recipient_count'] = count($ent_scenarios);
            $ent_aggregates['got_phished'] = 0;
            $ent_aggregates['reported_phish'] = 0;
            $ent_aggregates['new_or_repeat'] = 0;
            $ent_aggregates['submitted_data'] = 0;
            $ent_aggregates['entered_password'] = 0;
            $ent_aggregates['districts'] = [];
            $ent_aggregates['percent_phished'] = 0.0;
            $ent_aggregates['percent_reported'] = 0.0;
            $ent_aggregates['percent_new_or_repeat'] = 0.0;
            $ent_aggregates['percent_submitted_data'] = 0.0;
            $ent_aggregates['percent_entered_password'] = 0.0;

            foreach ($ent_scenarios as $data) {
                if (strcmp($data['scenario_type'], 'App\PhishMe\AttachmentScenario') == 0) {
                    if (strcmp($data['viewed_education'], 'Yes') == 0) {
                        $ent_aggregates['got_phished']++;

                        if (array_key_exists($data['department'], $ent_aggregates['districts'])) {
                            $ent_aggregates['districts'][$data['department']]++;
                        } else {
                            $ent_aggregates['districts'][$data['department']] = 1;
                        }
                    }

                    if (strcmp($data['reported_phish'], 'Yes') == 0) {
                        $ent_aggregates['reported_phish']++;
                    }

                    if ($data['new_repeat_reporter']) {
                        $ent_aggregates['new_or_repeat']++;
                    }
                } elseif (strcmp($data['scenario_type'], 'App\PhishMe\ClickOnlyScenario') == 0) {
                    if (strcmp($data['clicked_link'], 'Yes') == 0) {
                        $ent_aggregates['got_phished']++;

                        if (array_key_exists($data['department'], $ent_aggregates['districts'])) {
                            $ent_aggregates['districts'][$data['department']]++;
                        } else {
                            $ent_aggregates['districts'][$data['department']] = 1;
                        }
                    }

                    if (strcmp($data['reported_phish'], 'Yes') == 0) {
                        $ent_aggregates['reported_phish']++;
                    }

                    if ($data['new_repeat_reporter']) {
                        $ent_aggregates['new_or_repeat']++;
                    }
                } elseif (strcmp($data['scenario_type'], 'App\PhishMe\DataEntryScenario') == 0) {
                    if (strcmp($data['clicked_link'], 'Yes') == 0) {
                        $ent_aggregates['got_phished']++;

                        if (array_key_exists($data['department'], $ent_aggregates['districts'])) {
                            $ent_aggregates['districts'][$data['department']]++;
                        } else {
                            $ent_aggregates['districts'][$data['department']] = 1;
                        }
                    }

                    if (strcmp($data['submitted_data'], 'Yes') == 0) {
                        $ent_aggregates['submitted_data']++;
                    }

                    if (strcmp($data['entered_password'], 'Yes') == 0) {
                        $ent_aggregates['entered_password']++;
                    }

                    if (strcmp($data['reported_phish'], 'Yes') == 0) {
                        $ent_aggregates['reported_phish']++;
                    }

                    if ($data['new_repeat_reporter']) {
                        $ent_aggregates['new_or_repeat']++;
                    }
                }
            }

            $phished_perc = ($ent_aggregates['got_phished'] / count($ent_scenarios)) * 100;
            $report_perc = ($ent_aggregates['reported_phish'] / count($ent_scenarios)) * 100;
            $new_repeat_perc = ($ent_aggregates['new_or_repeat'] / count($ent_scenarios)) * 100;
            $submit_data_perc = ($ent_aggregates['submitted_data'] / count($ent_scenarios)) * 100;
            $submit_pwd_perc = ($ent_aggregates['entered_password'] / count($ent_scenarios)) * 100;

            $ent_aggregates['percent_phished'] = floatval(number_format($phished_perc, 2));
            $ent_aggregates['percent_reported'] = floatval(number_format($report_perc, 2));
            $ent_aggregates['percent_new_or_repeat'] = floatval(number_format($new_repeat_perc, 2));
            $ent_aggregates['percent_submitted_data'] = floatval(number_format($submit_data_perc, 2));
            $ent_aggregates['percent_entered_password'] = floatval(number_format($submit_pwd_perc, 2));

            // ktg aggregates
            $ktg_aggregates['recipient_count'] = count($ktg_scenarios);
            $ktg_aggregates['got_phished'] = 0;
            $ktg_aggregates['reported_phish'] = 0;
            $ktg_aggregates['new_or_repeat'] = 0;
            $ktg_aggregates['submitted_data'] = 0;
            $ktg_aggregates['entered_password'] = 0;
            $ktg_aggregates['districts'] = [];
            $ktg_aggregates['percent_phished'] = 0.0;
            $ktg_aggregates['percent_reported'] = 0.0;
            $ktg_aggregates['percent_new_or_repeat'] = 0.0;
            $ktg_aggregates['percent_submitted_data'] = 0.0;
            $ktg_aggregates['percent_entered_password'] = 0.0;

            foreach ($ktg_scenarios as $data) {
                if (strcmp($data['scenario_type'], 'App\PhishMe\AttachmentScenario') == 0) {
                    if (strcmp($data['viewed_education'], 'Yes') == 0) {
                        $ktg_aggregates['got_phished']++;

                        if (array_key_exists($data['department'], $ktg_aggregates['districts'])) {
                            $ktg_aggregates['districts'][$data['department']]++;
                        } else {
                            $ktg_aggregates['districts'][$data['department']] = 1;
                        }
                    }

                    if (strcmp($data['reported_phish'], 'Yes') == 0) {
                        $ktg_aggregates['reported_phish']++;
                    }

                    if ($data['new_repeat_reporter']) {
                        $ktg_aggregates['new_or_repeat']++;
                    }
                } elseif (strcmp($data['scenario_type'], 'App\PhishMe\ClickOnlyScenario') == 0) {
                    if (strcmp($data['clicked_link'], 'Yes') == 0) {
                        $ktg_aggregates['got_phished']++;

                        if (array_key_exists($data['department'], $ktg_aggregates['districts'])) {
                            $ktg_aggregates['districts'][$data['department']]++;
                        } else {
                            $ktg_aggregates['districts'][$data['department']] = 1;
                        }
                    }

                    if (strcmp($data['reported_phish'], 'Yes') == 0) {
                        $ktg_aggregates['reported_phish']++;
                    }

                    if ($data['new_repeat_reporter']) {
                        $ktg_aggregates['new_or_repeat']++;
                    }
                } elseif (strcmp($data['scenario_type'], 'App\PhishMe\DataEntryScenario') == 0) {
                    if (strcmp($data['clicked_link'], 'Yes') == 0) {
                        $ktg_aggregates['got_phished']++;

                        if (array_key_exists($data['department'], $ktg_aggregates['districts'])) {
                            $ktg_aggregates['districts'][$data['department']]++;
                        } else {
                            $ktg_aggregates['districts'][$data['department']] = 1;
                        }
                    }

                    if (strcmp($data['submitted_data'], 'Yes') == 0) {
                        $ktg_aggregates['submitted_data']++;
                    }

                    if (strcmp($data['entered_password'], 'Yes') == 0) {
                        $ktg_aggregates['entered_password']++;
                    }

                    if (strcmp($data['reported_phish'], 'Yes') == 0) {
                        $ktg_aggregates['reported_phish']++;
                    }

                    if ($data['new_repeat_reporter']) {
                        $ktg_aggregates['new_or_repeat']++;
                    }
                }
            }

            $phished_perc = ($ktg_aggregates['got_phished'] / count($ktg_scenarios)) * 100;
            $report_perc = ($ktg_aggregates['reported_phish'] / count($ktg_scenarios)) * 100;
            $new_repeat_perc = ($ktg_aggregates['new_or_repeat'] / count($ktg_scenarios)) * 100;
            $submit_data_perc = ($ktg_aggregates['submitted_data'] / count($ktg_scenarios)) * 100;
            $submit_pwd_perc = ($ktg_aggregates['entered_password'] / count($ktg_scenarios)) * 100;

            $ktg_aggregates['percent_phished'] = floatval(number_format($phished_perc, 2));
            $ktg_aggregates['percent_reported'] = floatval(number_format($report_perc, 2));
            $ktg_aggregates['percent_new_or_repeat'] = floatval(number_format($new_repeat_perc, 2));
            $ktg_aggregates['percent_submitted_data'] = floatval(number_format($submit_data_perc, 2));
            $ktg_aggregates['percent_entered_password'] = floatval(number_format($submit_pwd_perc, 2));

            // respond
            $response = [
                'success'           => true,
                'date'              => $date,
                'ent_aggregates'    => $ent_aggregates,
                'ktg_aggregates'    => $ktg_aggregates,
            ];
        } catch (\Exception $e) {
            Log::info('Failed to get click test result aggregates: '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get click test result aggregates',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get results for a particular scenario.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEnterpriseClickTestResults($date, $district)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            if (strcmp($district, 'All') == 0) {
                // get attachment, click only and data entry scenario results on a particular scenario for all Districts
                $attachment_results = AttachmentScenario::where([
                        ['scenario_title', '=', $date.' Enterprise Click Test'],
                    ])->select(
                        'scenario_title',
                        'scenario_type',
                        'recipient_name',
                        'department',
                        'viewed_education',
                        'reported_phish',
                        'new_repeat_reporter',
                        'time_to_report'
                    )->get();

                $click_only_results = ClickOnlyScenario::where([
                        ['scenario_title', '=', $date.' Enterprise Click Test'],
                    ])->select(
                        'scenario_title',
                        'scenario_type',
                        'recipient_name',
                        'department',
                        'clicked_link',
                        'reported_phish',
                        'new_repeat_reporter',
                        'time_to_report',
                        'seconds_spent_on_education'
                    )->get();

                $data_entry_results = DataEntryScenario::where([
                        ['scenario_title', '=', $date.' Enterprise Click Test'],
                    ])->select(
                        'scenario_title',
                        'scenario_type',
                        'recipient_name',
                        'department',
                        'clicked_link',
                        'submitted_form',
                        'submitted_data',
                        'phished_username',
                        'entered_password',
                        'reported_phish',
                        'new_repeat_reporter',
                        'time_to_report',
                        'seconds_spent_on_education'
                    )->get();
            } else {
                // get attachment, click only and data entry scenario results on a particular scenario for a particular District
                $attachment_results = AttachmentScenario::where([
                        ['scenario_title', '=', $date.' Enterprise Click Test'],
                        ['department', '=', $district],
                    ])->select(
                        'scenario_title',
                        'scenario_type',
                        'recipient_name',
                        'department',
                        'viewed_education',
                        'reported_phish',
                        'new_repeat_reporter',
                        'time_to_report'
                    )->get();

                $click_only_results = ClickOnlyScenario::where([
                        ['scenario_title', '=', $date.' Enterprise Click Test'],
                        ['department', '=', $district],
                    ])->select(
                        'scenario_title',
                        'scenario_type',
                        'recipient_name',
                        'department',
                        'clicked_link',
                        'reported_phish',
                        'new_repeat_reporter',
                        'time_to_report',
                        'seconds_spent_on_education'
                    )->get();

                $data_entry_results = DataEntryScenario::where([
                        ['scenario_title', '=', $date.' Enterprise Click Test'],
                        ['department', '=', $district],
                    ])->select(
                        'scenario_title',
                        'scenario_type',
                        'recipient_name',
                        'department',
                        'clicked_link',
                        'submitted_form',
                        'submitted_data',
                        'phished_username',
                        'entered_password',
                        'reported_phish',
                        'new_repeat_reporter',
                        'time_to_report',
                        'seconds_spent_on_education'
                    )->get();
            }

            // cycle through each of the returned results and build your return array
            foreach ($attachment_results as $result) {
                if ($result['time_to_report'] > 0) {
                    $time_to_report = $result['time_to_report'];
                } else {
                    $time_to_report = 0;
                }

                $scenario_type = explode('\\', $result['scenario_type'])[2];

                $data[] = [
                    'scenario_title'                 => $result['scenario_title'],
                    'scenario_type'                  => $scenario_type,
                    'recipient_name'                 => $result['recipient_name'],
                    'district'                       => $result['department'],
                    'viewed_education'               => $result['viewed_education'],
                    'clicked_link'                   => 'n/a',
                    'submitted_form'                 => 'n/a',
                    'submitted_data'                 => 'n/a',
                    'phished_username'               => 'n/a',
                    'entered_password'               => 'n/a',
                    'reported_phish'                 => $result['reported_phish'],
                    'new_repeat_reporter'            => $result['new_repeat_reporter'],
                    'time_to_report'                 => $time_to_report,
                    'seconds_spent_on_education'     => 0,
                ];
            }

            foreach ($click_only_results as $result) {
                if ($result['time_to_report'] > 0) {
                    $time_to_report = $result['time_to_report'];
                } else {
                    $time_to_report = 0;
                }

                $scenario_type = explode('\\', $result['scenario_type'])[2];

                $data[] = [
                    'scenario_title'                 => $result['scenario_title'],
                    'scenario_type'                  => $scenario_type,
                    'recipient_name'                 => $result['recipient_name'],
                    'district'                       => $result['department'],
                    'viewed_education'               => 'n/a',
                    'clicked_link'                   => $result['clicked_link'],
                    'submitted_form'                 => 'n/a',
                    'submitted_data'                 => 'n/a',
                    'phished_username'               => 'n/a',
                    'entered_password'               => 'n/a',
                    'reported_phish'                 => $result['reported_phish'],
                    'new_repeat_reporter'            => $result['new_repeat_reporter'],
                    'time_to_report'                 => $time_to_report,
                    'seconds_spent_on_education'     => $result['seconds_spent_on_education'],
                ];
            }

            foreach ($data_entry_results as $result) {
                if ($result['time_to_report'] > 0) {
                    $time_to_report = $result['time_to_report'];
                } else {
                    $time_to_report = 0;
                }

                $scenario_type = explode('\\', $result['scenario_type'])[2];

                $data[] = [
                    'scenario_title'                 => $result['scenario_title'],
                    'scenario_type'                  => $scenario_type,
                    'recipient_name'                 => $result['recipient_name'],
                    'district'                       => $result['department'],
                    'viewed_education'               => 'n/a',
                    'clicked_link'                   => $result['clicked_link'],
                    'submitted_form'                 => $result['submitted_form'],
                    'submitted_data'                 => $result['submitted_data'],
                    'phished_username'               => $result['phished_username'],
                    'entered_password'               => $result['entered_password'],
                    'reported_phish'                 => $result['reported_phish'],
                    'new_repeat_reporter'            => $result['new_repeat_reporter'],
                    'time_to_report'                 => $time_to_report,
                    'seconds_spent_on_education'     => $result['seconds_spent_on_education'],
                ];
            }

            $response = [
                'success'           => true,
                'count'             => count($data),
                'scenario_results'  => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get click test results for '.$district.' during '.$date.': '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get click test results for '.$district.' during '.$date,
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get KTG click test results for a particular scenario.
     *
     * @return \Illuminate\Http\Response
     */
    public function getKTGClickTestResults($date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            // get attachment, click only and data entry scenario results on a particular scenario for all Districts
            $attachment_results = AttachmentScenario::where('scenario_title', 'like', $date.' KTG Click Test%')
                ->select(
                    'scenario_title',
                    'scenario_type',
                    'recipient_name',
                    'department',
                    'viewed_education',
                    'reported_phish',
                    'new_repeat_reporter',
                    'time_to_report'
                )->get();

            $click_only_results = ClickOnlyScenario::where('scenario_title', 'like', $date.' KTG Click Test%')
                ->select(
                    'scenario_title',
                    'scenario_type',
                    'recipient_name',
                    'department',
                    'clicked_link',
                    'reported_phish',
                    'new_repeat_reporter',
                    'time_to_report',
                    'seconds_spent_on_education'
                )->get();

            $data_entry_results = DataEntryScenario::where('scenario_title', 'like', $date.' KTG Click Test%')
                ->select(
                    'scenario_title',
                    'scenario_type',
                    'recipient_name',
                    'department',
                    'clicked_link',
                    'submitted_form',
                    'submitted_data',
                    'phished_username',
                    'entered_password',
                    'reported_phish',
                    'new_repeat_reporter',
                    'time_to_report',
                    'seconds_spent_on_education'
                )->get();

            // cycle through each of the returned results and build your return array
            foreach ($attachment_results as $result) {
                if ($result['time_to_report'] > 0) {
                    $time_to_report = $result['time_to_report'];
                } else {
                    $time_to_report = 0;
                }

                $scenario_type = explode('\\', $result['scenario_type'])[2];

                $data[] = [
                    'scenario_title'                 => $result['scenario_title'],
                    'scenario_type'                  => $scenario_type,
                    'recipient_name'                 => $result['recipient_name'],
                    'district'                       => $result['department'],
                    'viewed_education'               => $result['viewed_education'],
                    'clicked_link'                   => 'n/a',
                    'submitted_form'                 => 'n/a',
                    'submitted_data'                 => 'n/a',
                    'phished_username'               => 'n/a',
                    'entered_password'               => 'n/a',
                    'reported_phish'                 => $result['reported_phish'],
                    'new_repeat_reporter'            => $result['new_repeat_reporter'],
                    'time_to_report'                 => $time_to_report,
                    'seconds_spent_on_education'     => 0,
                ];
            }

            foreach ($click_only_results as $result) {
                if ($result['time_to_report'] > 0) {
                    $time_to_report = $result['time_to_report'];
                } else {
                    $time_to_report = 0;
                }

                $scenario_type = explode('\\', $result['scenario_type'])[2];

                $data[] = [
                    'scenario_title'                 => $result['scenario_title'],
                    'scenario_type'                  => $scenario_type,
                    'recipient_name'                 => $result['recipient_name'],
                    'district'                       => $result['department'],
                    'viewed_education'               => 'n/a',
                    'clicked_link'                   => $result['clicked_link'],
                    'submitted_form'                 => 'n/a',
                    'submitted_data'                 => 'n/a',
                    'phished_username'               => 'n/a',
                    'entered_password'               => 'n/a',
                    'reported_phish'                 => $result['reported_phish'],
                    'new_repeat_reporter'            => $result['new_repeat_reporter'],
                    'time_to_report'                 => $time_to_report,
                    'seconds_spent_on_education'     => $result['seconds_spent_on_education'],
                ];
            }

            foreach ($data_entry_results as $result) {
                if ($result['time_to_report'] > 0) {
                    $time_to_report = $result['time_to_report'];
                } else {
                    $time_to_report = 0;
                }

                $scenario_type = explode('\\', $result['scenario_type'])[2];

                $data[] = [
                    'scenario_title'                 => $result['scenario_title'],
                    'scenario_type'                  => $scenario_type,
                    'recipient_name'                 => $result['recipient_name'],
                    'district'                       => $result['department'],
                    'viewed_education'               => 'n/a',
                    'clicked_link'                   => $result['clicked_link'],
                    'submitted_form'                 => $result['submitted_form'],
                    'submitted_data'                 => $result['submitted_data'],
                    'phished_username'               => $result['phished_username'],
                    'entered_password'               => $result['entered_password'],
                    'reported_phish'                 => $result['reported_phish'],
                    'new_repeat_reporter'            => $result['new_repeat_reporter'],
                    'time_to_report'                 => $time_to_report,
                    'seconds_spent_on_education'     => $result['seconds_spent_on_education'],
                ];
            }

            $response = [
                'success'           => true,
                'count'             => count($data),
                'scenario_results'  => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get click test results for '.$district.' during '.$date.': '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get click test results for '.$district.' during '.$date,
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**********************************
     * Attachment scenario functions. *
     **********************************/

    /**
     * Get attachment scenarios for a given user.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserAttachmentScenarios($user_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $clicked = 0;
            $reported = 0;
            $mobile = 0;
            $repeat = 0;
            $education_time = 0;

            $scenarios = AttachmentScenario::where('recipient_name', '=', $user_name)->get();

            foreach ($scenarios as $scenario) {
                if ($scenario['viewed_education'] == 'Yes') {
                    $clicked++;
                }

                if ($scenario['reported_phish'] == 'Yes') {
                    $reported++;
                }

                if ($scenario['mobile']) {
                    $mobile++;
                }

                if ($scenario['new_repeat_reporter'] == 'Repeat') {
                    $repeat++;
                }

                $data[] = \Metaclassing\Utility::decodeJson($scenario['data']);

                $education_time += $scenario['seconds_spent_on_education'];
            }

            $response = [
                'success'               => true,
                'count'                 => count($data),
                'clicked_count'         => $clicked,
                'reported_count'        => $reported,
                'repeat_count'          => $repeat,
                'mobile_count'          => $mobile,
                'avg_education_time'    => ($education_time / count($data)),
                'scenarios'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get attachment scenarios for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }

    /**********************************
     * Click only scenario functions. *
     **********************************/

    /**
     * Get click only scenarios for a given user.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserClickOnlyScenarios($user_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $clicked = 0;
            $reported = 0;
            $mobile = 0;
            $repeat = 0;
            $education_time = 0;

            $scenarios = ClickOnlyScenario::where('recipient_name', '=', $user_name)->get();

            foreach ($scenarios as $scenario) {
                if ($scenario['clicked_link'] == 'Yes') {
                    $clicked++;
                }

                if ($scenario['reported_phish'] == 'Yes') {
                    $reported++;
                }

                if ($scenario['mobile']) {
                    $mobile++;
                }

                if ($scenario['new_repeat_reporter'] == 'Repeat') {
                    $repeat++;
                }

                $data[] = \Metaclassing\Utility::decodeJson($scenario['data']);

                $education_time += $scenario['seconds_spent_on_education'];
            }

            $response = [
                'success'               => true,
                'count'                 => count($data),
                'clicked_count'         => $clicked,
                'reported_count'        => $reported,
                'repeat_count'          => $repeat,
                'mobile_count'          => $mobile,
                'avg_education_time'    => ($education_time / count($data)),
                'scenarios'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get click only scenarios for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }

    /**********************************
     * Data entry scenario functions. *
     **********************************/

    /**
     * Get data entry scenarios for a given user.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserDataEntryScenarios($user_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $phished_usernames = [];
            $clicked = 0;
            $submitted_form = 0;
            $submitted_data = 0;
            $entered_password = 0;
            $reported = 0;
            $mobile = 0;
            $repeat = 0;
            $education_time = 0;

            $scenarios = DataEntryScenario::where('recipient_name', '=', $user_name)->get();

            foreach ($scenarios as $scenario) {
                if ($scenario['clicked_link'] == 'Yes') {
                    $clicked++;
                }

                if ($scenario['submitted_form'] == 'Yes') {
                    $submitted_form++;
                }

                if ($scenario['submitted_data'] == 'Yes') {
                    $submitted_data++;
                }

                if ($scenario['entered_password'] == 'Yes') {
                    $entered_password++;
                }

                if ($scenario['reported_phish'] == 'Yes') {
                    $reported++;
                }

                if ($scenario['mobile']) {
                    $mobile++;
                }

                if ($scenario['new_repeat_reporter'] == 'Repeat') {
                    $repeat++;
                }

                if ($scenario['phished_username']) {
                    $phished_usernames[] = $scenario['phished_username'];
                }

                $data[] = \Metaclassing\Utility::decodeJson($scenario['data']);

                $education_time += $scenario['seconds_spent_on_education'];
            }

            $response = [
                'success'               => true,
                'count'                 => count($data),
                'clicked_count'         => $clicked,
                'submitted_form_count'  => $submitted_form,
                'submitted_data_count'  => $submitted_data,
                'reported_count'        => $reported,
                'repeat_count'          => $repeat,
                'mobile_count'          => $mobile,
                'avg_education_time'    => ($education_time / count($data)),
                'phished_usernames'     => $phished_usernames,
                'scenarios'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get data entry scenarios for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }
}
