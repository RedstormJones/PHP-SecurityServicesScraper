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
     * Get all scenario titles.
     *
     * @return \Illuminate\Http\Response
     */
    public function getScenarioTitles()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $title_regex = '/(\d{4}-\S{3}\s)/';

            $attachment_scenarios = AttachmentScenario::oldest('scenario_title')->select('scenario_title')->distinct()->get();
            $click_only_scenarios = ClickOnlyScenario::oldest('scenario_title')->select('scenario_title')->distinct()->get();
            $data_entry_scenarios = DataEntryScenario::oldest('scenario_title')->select('scenario_title')->distinct()->get();

            foreach ($attachment_scenarios as $scenario) {
                if (preg_match($title_regex, $scenario['scenario_title'], $hits)) {
                    //$scenario_date = substr($scenario['scenario_title'], 0, 8);
                    $scenario_date = $hits[1];

                    Log::info($scenario['scenario_title'].' - '.$scenario_date);

                    $data[] = $scenario['scenario_title'];
                }
            }

            foreach ($click_only_scenarios as $scenario) {
                if (preg_match($title_regex, $scenario['scenario_title'], $hits)) {
                    //$scenario_date = substr($scenario['scenario_title'], 0, 8);
                    $scenario_date = $hits[1];

                    Log::info($scenario['scenario_title'].' - '.$scenario_date);

                    $data[] = $scenario['scenario_title'];
                }
            }

            foreach ($data_entry_scenarios as $scenario) {
                if (preg_match($title_regex, $scenario['scenario_title'], $hits)) {
                    //$scenario_date = substr($scenario['scenario_title'], 0, 8);
                    $scenario_date = $hits[1];

                    Log::info($scenario['scenario_title'].' - '.$scenario_date);

                    $data[] = $scenario['scenario_title'];
                }
            }

            $response = [
                'success'   => true,
                'count'     => count($data),
                'scenarios' => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get PhishMe scenario titles.',
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

            // get attachment, click only and data entry scenario results for a particular scenario and District
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
                    'seconds_spent_on_education'
                )->get();

            // cycle through each of the returned results and build your return array
            foreach ($attachment_results as $result) {
                $data[] = [
                    'scenario_title'                => $result['scenario_title'],
                    'scenario_type'                 => $result['scenario_type'],
                    'recipient_name'                => $result['recipient_name'],
                    'department'                    => $result['department'],
                    'viewed_eduation'               => $result['viewed_education'],
                    'clicked_link'                  => 'n/a',
                    'submitted_form'                => 'n/a',
                    'submitted_data'                => 'n/a',
                    'phished_username'              => 'n/a',
                    'entered_password'              => 'n/a',
                    'reported_phish'                => $result['reported_phish'],
                    'new_repeat_reporter'           => $result['new_repeat_reporter'],
                    'time_to_report'                => $result['time_to_report'],
                    'seconds_spent_on_education'    => 'n/a',
                ];
            }

            foreach ($click_only_results as $result) {
                $data[] = [
                    'scenario_title'                => $result['scenario_title'],
                    'scenario_type'                 => $result['scenario_type'],
                    'recipient_name'                => $result['recipient_name'],
                    'department'                    => $result['department'],
                    'viewed_eduation'               => 'n/a',
                    'clicked_link'                  => $result['clicked_link'],
                    'submitted_form'                => 'n/a',
                    'submitted_data'                => 'n/a',
                    'phished_username'              => 'n/a',
                    'entered_password'              => 'n/a',
                    'reported_phish'                => $result['reported_phish'],
                    'new_repeat_reporter'           => $result['new_repeat_reporter'],
                    'time_to_report'                => $result['time_to_report'],
                    'seconds_spent_on_education'    => $result['seconds_spent_on_education'],
                ];
            }

            foreach ($data_entry_results as $result) {
                $data[] = [
                    'scenario_title'                => $result['scenario_title'],
                    'scenario_type'                 => $result['scenario_type'],
                    'recipient_name'                => $result['recipient_name'],
                    'department'                    => $result['department'],
                    'viewed_eduation'               => 'n/a',
                    'clicked_link'                  => $result['clicked_link'],
                    'submitted_form'                => $result['submitted_form'],
                    'submitted_data'                => $result['submitted_data'],
                    'phished_username'              => $result['phished_username'],
                    'entered_password'              => $result['entered_password'],
                    'reported_phish'                => $result['reported_phish'],
                    'new_repeat_reporter'           => $result['new_repeat_reporter'],
                    'time_to_report'                => $result['time_to_report'],
                    'seconds_spent_on_education'    => $result['seconds_spent_on_education'],
                ];
            }

            $response = [
                'success'           => true,
                'count'             => count($data),
                'scenario_results'  => $data,
            ];
        } catch (\Exception $e) {
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
