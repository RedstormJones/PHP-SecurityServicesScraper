<?php

namespace App\Http\Controllers;

use App\IronPort\IncomingEmail;
use Tymon\JWTAuth\Facades\JWTAuth;


class IronPortController extends Controller
{
    /**
     * Create new IronPort Controller instance.
     *
     * @return void
     */
    public function __constuct()
    {
        $this->middleware('auth');
    }



    /**
     * Get total count of incoming email
     * 
     * @return \Illuminate\Http\Response
     */
    public function getTotalCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $incoming_email_count = IncomingEmail::count();

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($incoming_email_count),
                'incoming_email_count'  => $incoming_email_count,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get count of incoming email',
            ];
        }

        return response()->json($response);
    }


    /**
     * Get count of incoming email in a particular date range
     * 
     * @return \Illuminate\Http\Response
     */
    public function getEmailCountInDateRange($from_date, $to_date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $incoming_emails_count = IncomingEmail::where([
                    ['begin_date', '>=', $from_date],
                    ['end_date', '<=', $to_date],
                ])->count();

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($incoming_emails_count),
                'incoming_emails_count'   => $incoming_emails_count
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get count of incoming email in specified date range: '.$from_date.' - '.$to_date,
            ];
        }

        return response()->json($response);
    }


    /**
     * Get incoming email sent by a particular sending domain
     * 
     * @return \Illuminate\Http\Response
     */
    public function getEmailsBySendingDomain($sending_domain)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $incoming_emails = IncomingEmail::where('sender_domain', '=', $sending_domain)->pluck('data');

            foreach($incoming_emails as $incoming_email)
            {
                $data[] = \Metaclassing\Utility::decodeJson($incoming_email);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($data),
                'incoming_emails_count'   => $data
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get incoming email from sending domain: '.$sending_domain,
            ];
        }

        return response()->json($response);
    }


    /**
     * Get incoming email sent within a particular date range
     * 
     * @return \Illuminate\Http\Response
     */
    public function getEmailsInDateRange($from_date, $to_date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            /*
            $incoming_emails = IncomingEmail::where([
                    ['begin_date', '>=', $from_date],
                    ['end_date', '<=', $to_date],
                ])->pluck('data');

            foreach($incoming_emails as $incoming_email)
            {
                $data[] = \Metaclassing\Utility::decodeJson($incoming_email);
            }
            /**/

            foreach(IncomingEmail::where([
                    ['begin_date', '>=', $from_date],
                    ['end_date', '<=', $to_date],
                ])->cursor() as $incoming_email)
            {
                $data[] = \Metaclassing\Utility::decodeJson($incoming_email);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($data),
                'incoming_emails'   => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get count of incoming email in specified date range: '.$from_date.' - '.$to_date,
            ];
        }

        return response()->json($response);
    }

}
