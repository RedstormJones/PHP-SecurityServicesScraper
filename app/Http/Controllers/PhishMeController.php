<?php

namespace App\Http\Controllers;

use App\PhishMe\AttachmentScenario;
use App\PhishMe\ClickOnlyScenario;
use App\PhishMe\DataEntryScenario;
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
     * 
     * Attachment scenario functions
     * 
     */
    
    /**
     * Get attachment scenarios for a given user
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

            foreach($scenarios as $scenario)
            {
                if ($scenario['viewed_education'] == 'Yes')
                {
                    $clicked++;
                }

                if ($scenario['reported_phish'] == 'Yes')
                {
                    $reported++;
                }

                if ($scenario['mobile'])
                {
                    $mobile++;
                }

                if ($scenario['new_repeat_reporter'] == 'Repeat')
                {
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

        }
        catch (\Exception $e)
        {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get attachment scenarios for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }


    /**
     * 
     * Click only scenario functions
     * 
     */


    /**
     * Get click only scenarios for a given user
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

            foreach($scenarios as $scenario)
            {
                if ($scenario['clicked_link'] == 'Yes')
                {
                    $clicked++;
                }

                if ($scenario['reported_phish'] == 'Yes')
                {
                    $reported++;
                }

                if ($scenario['mobile'])
                {
                    $mobile++;
                }

                if ($scenario['new_repeat_reporter'] == 'Repeat')
                {
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

        }
        catch (\Exception $e)
        {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get click only scenarios for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }





    /**
     * 
     * Data entry scenario functions
     * 
     */
    

    /**
     * Get data entry scenarios for a given user
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

            foreach($scenarios as $scenario)
            {
                if ($scenario['clicked_link'] == 'Yes')
                {
                    $clicked++;
                }

                if ($scenario['submitted_form'] == 'Yes')
                {
                    $submitted_form++;
                }

                if ($scenario['submitted_data'] == 'Yes')
                {
                    $submitted_data++;
                }

                if ($scenario['entered_password'] == 'Yes')
                {
                    $entered_password++;
                }

                if ($scenario['reported_phish'] == 'Yes')
                {
                    $reported++;
                }

                if ($scenario['mobile'])
                {
                    $mobile++;
                }

                if ($scenario['new_repeat_reporter'] == 'Repeat')
                {
                    $repeat++;
                }

                if ($scenario['phished_username'])
                {
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

        }
        catch (\Exception $e)
        {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get data entry scenarios for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }
}
