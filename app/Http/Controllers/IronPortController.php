<?php

namespace App\Http\Controllers;

use App\IronPort\IncomingEmail;
use App\IronPort\IronPortThreat;
use App\IronPort\IronPortSpamEmail;

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
     * Get total count of incoming email.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotalCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $incoming_email_count = IncomingEmail::count();

            $response = [
                'success'               => true,
                'total'                 => count($incoming_email_count),
                'incoming_email_count'  => $incoming_email_count,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get count of incoming email',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get count of incoming email in a particular date range.
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
                'success'                 => true,
                'total'                   => count($incoming_emails_count),
                'incoming_emails_count'   => $incoming_emails_count,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get count of incoming email in specified date range: '.$from_date.' - '.$to_date,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get incoming email sent by a particular sending domain.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEmailsBySendingDomain($sending_domain)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $incoming_emails = IncomingEmail::where('sender_domain', '=', $sending_domain)->paginate(100);

            foreach ($incoming_emails as $incoming_email) {
                $data[] = \Metaclassing\Utility::decodeJson($incoming_email['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $incoming_emails->total(),
                'current_page'      => $incoming_emails->currentPage(),
                'next_page_url'     => $incoming_emails->nextPageUrl(),
                'results_per_page'  => $incoming_emails->perPage(),
                'has_more_pages'    => $incoming_emails->hasMorePages(),
                'incoming_emails'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get incoming email from sending domain: '.$sending_domain,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get incoming email sent within a particular date range.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEmailsInDateRange($from_date, $to_date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $incoming_emails = IncomingEmail::where([
                    ['begin_date', '>=', $from_date],
                    ['end_date', '<=', $to_date],
                ])->paginate(100);

            foreach($incoming_emails as $incoming_email)
            {
                $data[] = \Metaclassing\Utility::decodeJson($incoming_email['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $incoming_emails->total(),
                'current_page'      => $incoming_emails->currentPage(),
                'next_page_url'     => $incoming_emails->nextPageUrl(),
                'results_per_page'  => $incoming_emails->perPage(),
                'has_more_pages'    => $incoming_emails->hasMorePages(),
                'incoming_emails'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get count of incoming email in specified date range: '.$from_date.' - '.$to_date,
            ];
        }

        return response()->json($response);
    }




    /**
    * Get all IronPort threats
    *
    * @return \Illuminate\Http\Response
    */
    public function getAllThreats()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = IronPortThreat::all();

            foreach($threats as $threat)
            {
                $data[] = \Metaclassing\Utility::decodeJson($threat['data']);
            }

            $response = [
                'success'   => true,
                'total'     => count($data),
                'threats'   => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort threats.',
            ];
        }

        return response()->json($response);
    }

    /**
    * Get total count of IronPort threats
    *
    * @return \Illuminate\Http\Response
    */
    public function getTotalThreatCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $count = 0;

            $threats = IronPortThreat::all();

            foreach($threats as $threat)
            {
                $count += $threat['total_messages'];
            }

            $response = [
                'success'       => true,
                'threat_count'  => $count,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort threat count.',
            ];
        }

        return response()->json($response);
    }



    /**
    * Get count of IronPort threats for a specific month
    *
    * @return \Illuminate\Http\Response
    */
    public function getThreatCountByDate($date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $threat_count = IronPortThreat::where('begin_date', 'like', $date.'%')->pluck('total_messages');

            $response = [
                'success'   => true,
                'threat_count'  => $threat_count[0],
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get threat count for date: '.$date,
            ];
        }

        return response()->json($response);
    }




}
