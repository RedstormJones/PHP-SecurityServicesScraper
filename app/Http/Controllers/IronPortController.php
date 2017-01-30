<?php

namespace App\Http\Controllers;

use App\IronPort\IncomingEmail;
use App\IronPort\IronPortSpamEmail;
use App\IronPort\IronPortThreat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
                'message'   => 'Failed to get count of incoming email in specified date range: '.$from_date.' - '.$to_date,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get all IronPort threats.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllThreats()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = IronPortThreat::all();

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat['data']);
            }

            $response = [
                'success'   => true,
                'total'     => count($data),
                'threats'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort threats.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get total count of IronPort threats.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotalThreatCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $count = 0;

            $threats = IronPortThreat::all();

            foreach ($threats as $threat) {
                $count += $threat['total_messages'];
            }

            $response = [
                'success'       => true,
                'threat_count'  => $count,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort threat count.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get count of IronPort threats for a specific month.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatCountByDate($date)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $threat_count = IronPortThreat::where('begin_date', 'like', $date.'%')->pluck('total_messages');

            $response = [
                'success'       => true,
                'threat_count'  => $threat_count[0],
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get threat count for date: '.$date,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get count of IronPort spam emails.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotalSpamCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $spam_count = IronPortSpamEmail::count();

            $response = [
                'success'       => true,
                'spam_count'    => $spam_count,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort spam count.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get average count of IronPort spam emails for users.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAverageUserSpamCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $user_counts = [];

            $spam_recipients = IronPortSpamEmail::where([
                ['quarantine_names', 'like', '%Policy%'],
                ['time_added', '>=', '2017-01-01 00:00:00'],
            ])->pluck('recipients');

            Log::info('Policy spam quarantine recipients count: '.count($spam_recipients));

            foreach ($spam_recipients as $recipient) {
                if (!array_key_exists($recipient, $user_counts))
                {
                    $user_counts[$recipient] = 1;
                }
                else
                {
                    $user_counts[$recipient]++;
                }
            }

            //Log::info($user_counts);

            $total = 0;
            $count = count($user_counts);
            Log::info('total count: '.$count);

            foreach ($user_counts as $key => $value)
            {
                $total += $value;
            }

            $average_user_spam = floor($total / $count);


            $response = [
                'success'           => true,
                'average_user_spam_count'  => $average_user_spam,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get average user spam counts: '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get average user spam counts.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Takes a recipient and a date range and calculates spam counts for each date in the date range for this user.
     *
     * @return \Illuminate\Http\Response
     */
    public function calculateUserSpamCounts($recipient, $date_range)
    {
        $user_counts = [];

        $user_spam = IronPortSpamEmail::where([
            ['recipients', 'like', '%'.$recipient.'%'],
            ['quarantine_names', 'like', '%Policy%'],
            ['time_added', '>=', '2017-01-01 00:00:00'],
        ])->get();

        Log::info(count($user_spam).' spam emails found for '.$recipient);

        // build date keys and instantiate count values in user_counts array
        foreach ($date_range as $date) {
            $user_counts[$date] = 0;
        }

        // cycle through user's spam and enumerate counts
        foreach ($user_spam as $spam) {
            $time_added = Carbon::createFromFormat('Y-m-d H:i:s', $spam['time_added'])->toDateString();
            $user_counts[$time_added]++;
        }

        return $user_counts;
    }

    /**
     * Generates a date range array from the given start and end dates.
     *
     * @return \Illuminate\Http\Response
     */
    public function generateDateRange($start_date, $end_date)
    {
        $dates = [];

        for ($date = $start_date; $date->lte($end_date); $date->addDay()) {
            $dates[] = $date->toDateString();
        }
        Log::info(count($dates).' days have passed since the beginning of 2017');

        return $dates;
    }

    /**
     * Get IronPort spam emails sent by a specific sender address.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSpamBySender($sender)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $spam_emails = IronPortSpamEmail::where('sender', 'like', $sender)->paginate(100);

            foreach ($spam_emails as $spam) {
                $data[] = \Metaclassing\Utility::decodeJson($spam['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $spam_emails->total(),
                'current_page'      => $spam_emails->currentPage(),
                'next_page_url'     => $spam_emails->nextPageUrl(),
                'results_per_page'  => $spam_emails->perPage(),
                'has_more_pages'    => $spam_emails->hasMorePages(),
                'spam_emails'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort spam emails for sender: '.$sender,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get IronPort spam emails recieved by a specific recipient.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSpamByRecipient($recipient)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $spam_emails = IronPortSpamEmail::where('recipients', 'like', '%'.$recipient.'%')->paginate(100);

            foreach ($spam_emails as $spam) {
                $data[] = \Metaclassing\Utility::decodeJson($spam['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $spam_emails->total(),
                'current_page'      => $spam_emails->currentPage(),
                'next_page_url'     => $spam_emails->nextPageUrl(),
                'results_per_page'  => $spam_emails->perPage(),
                'has_more_pages'    => $spam_emails->hasMorePages(),
                'spam_emails'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort spam emails for recipient: '.$recipient,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get IronPort spam emails caught by a specific quarantine.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSpamByQuarantine($quarantine)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $spam_emails = IronPortSpamEmail::where('quarantine_names', 'like', '%'.$quarantine.'%')->paginate(100);

            foreach ($spam_emails as $spam) {
                $data[] = \Metaclassing\Utility::decodeJson($spam['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $spam_emails->total(),
                'current_page'      => $spam_emails->currentPage(),
                'next_page_url'     => $spam_emails->nextPageUrl(),
                'results_per_page'  => $spam_emails->perPage(),
                'has_more_pages'    => $spam_emails->hasMorePages(),
                'spam_emails'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort spam emails for quarantine: '.$quarantine,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get IronPort spam emails with a containing a specific subject.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSpamBySubject($subject)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $spam_emails = IronPortSpamEmail::where('subject', 'like', '%'.$subject.'%')->paginate(100);

            foreach ($spam_emails as $spam) {
                $data[] = \Metaclassing\Utility::decodeJson($spam['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $spam_emails->total(),
                'current_page'      => $spam_emails->currentPage(),
                'next_page_url'     => $spam_emails->nextPageUrl(),
                'results_per_page'  => $spam_emails->perPage(),
                'has_more_pages'    => $spam_emails->hasMorePages(),
                'spam_emails'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort spam emails for subject: '.$subject,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get IronPort spam emails by a specific reason.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSpamByReason($reason)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $spam_emails = IronPortSpamEmail::where('reason', 'like', '%'.$reason.'%')->paginate(100);

            foreach ($spam_emails as $spam) {
                $data[] = \Metaclassing\Utility::decodeJson($spam['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $spam_emails->total(),
                'current_page'      => $spam_emails->currentPage(),
                'next_page_url'     => $spam_emails->nextPageUrl(),
                'results_per_page'  => $spam_emails->perPage(),
                'has_more_pages'    => $spam_emails->hasMorePages(),
                'spam_emails'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get IronPort spam emails for reason: '.$reason,
            ];
        }

        return response()->json($response);
    }
}
