<?php

namespace App\Http\Controllers;

use App\ServiceNow\cmdbServer;
use App\ServiceNow\ServiceNowIncident;
use Tymon\JWTAuth\Facades\JWTAuth;

class ServiceNowController extends Controller
{
    /**
     * Create a new Cylance Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get all CMDB servers.
     *
     * @return void
     */
    public function getCMDBServers()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $servers = cmdbServer::paginate(100);

            foreach ($servers as $server) {
                $data[] = \Metaclassing\Utility::decodeJson($server['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $servers->total(),
                'count'             => $servers->count(),
                'current_page'      => $servers->currentPage(),
                'next_page_url'     => $servers->nextPageUrl(),
                'has_more_pages'    => $servers->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get CMDB servers.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get CMDB server by name.
     *
     * @return void
     */
    public function getCMDBServerByName($name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $server = cmdbServer::where('name', '=', $name)->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($server['data']);

            $response = [
                'success'    => true,
                'server'     => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get CMDB server: '.$name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get CMDB server by IP.
     *
     * @return void
     */
    public function getCMDBServerByIP($ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $server = cmdbServer::where('ip_address', '=', $ip)->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($server['data']);

            $response = [
                'success'    => true,
                'server'     => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get CMDB server for IP: '.$ip,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get CMDB servers by OS.
     *
     * @return void
     */
    public function getCMDBServersByOS($os)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $servers = cmdbServer::where('os', 'like', '%'.$os.'%')->get();

            foreach ($servers as $server) {
                $data[] = \Metaclassing\Utility::decodeJson($server['data']);
            }

            $response = [
                'success'      => true,
                'count'        => count($data),
                'servers'      => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get CMDB servers for operating system: '.$os,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get CMDB servers by District.
     *
     * @return void
     */
    public function getCMDBServersByDistrict($district)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $servers = cmdbServer::where('district', 'like', $district.'%')->get();

            if (count($servers) == 0) {
                throw new \Exception();
            } else {
                foreach ($servers as $server) {
                    $data[] = \Metaclassing\Utility::decodeJson($server['data']);
                }

                $response = [
                    'success'      => true,
                    'count'        => count($data),
                    'servers'      => $data,
                ];
            }
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get CMDB servers for District: '.$district,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get all Security incidents in ServiceNow.
     *
     * @return void
     */
    public function getAllSecurityIncidents()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowIncident::paginate(100);

            foreach ($tickets as $ticket) {
                $data[] = \Metaclassing\Utility::decodeJson($ticket['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $tickets->total(),
                'count'             => $tickets->count(),
                'current_page'      => $tickets->currentPage(),
                'next_page_url'     => $tickets->nextPageUrl(),
                'has_more_pages'    => $tickets->hasMorePages(),
                'incidents'         => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get Security incidents.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get active Security incidents in ServiceNow.
     *
     * @return void
     */
    public function getActiveSecurityIncidents()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowIncident::where([
                ['state', '!=', 'Closed'],
                ['state', '!=', 'Resolved'],
                ['state', '!=', 'Cancelled'],
            ])->paginate(100);

            foreach ($tickets as $ticket) {
                $data[] = \Metaclassing\Utility::decodeJson($ticket['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $tickets->total(),
                'count'             => $tickets->count(),
                'current_page'      => $tickets->currentPage(),
                'next_page_url'     => $tickets->nextPageUrl(),
                'has_more_pages'    => $tickets->hasMorePages(),
                'incidents'         => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get active Security incidents.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get active Security incidents by District.
     *
     * @return void
     */
    public function getSecurityIncidentsByDistrict($district)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            if (strlen($district) < 3 && $district != 'KU' && $district != 'ku') {
                throw new \Exception();
            } else {
                $tickets = ServiceNowIncident::where('district', 'like', $district.'%')->paginate(100);

                foreach ($tickets as $ticket) {
                    $data[] = \Metaclassing\Utility::decodeJson($ticket['data']);
                }

                $response = [
                    'success'           => true,
                    'total'             => $tickets->total(),
                    'count'             => $tickets->count(),
                    'current_page'      => $tickets->currentPage(),
                    'next_page_url'     => $tickets->nextPageUrl(),
                    'has_more_pages'    => $tickets->hasMorePages(),
                    'incidents'         => $data,
                ];
            }
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get Security incidents for district: '.$district,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get active Security incidents by District.
     *
     * @return void
     */
    public function getSecurityIncidentsByInitialAssignGroup($initial_group)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowIncident::where('initial_assignment_group', 'like', $initial_group.'%')->paginate(100);

            foreach ($tickets as $ticket) {
                $data[] = \Metaclassing\Utility::decodeJson($ticket['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $tickets->total(),
                'count'             => $tickets->count(),
                'current_page'      => $tickets->currentPage(),
                'next_page_url'     => $tickets->nextPageUrl(),
                'has_more_pages'    => $tickets->hasMorePages(),
                'incidents'         => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get Security incidents for initial assignment group: '.$initial_group,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get active Security incidents by priority.
     *
     * @return void
     */
    public function getSecurityIncidentsByPriority($priority)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowIncident::where('priority', 'like', $priority.'%')->paginate(100);

            foreach ($tickets as $ticket) {
                $data[] = \Metaclassing\Utility::decodeJson($ticket['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $tickets->total(),
                'count'             => $tickets->count(),
                'current_page'      => $tickets->currentPage(),
                'next_page_url'     => $tickets->nextPageUrl(),
                'has_more_pages'    => $tickets->hasMorePages(),
                'incidents'         => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get Security incidents for priority: '.$priority,
            ];
        }

        return response()->json($response);
    }


    /**
     * Get count of resolved tickets by user.
     *
     * @return void
     */
    public function getResolvedByUserCount()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $resolved_by = ServiceNowIncident::where('resolved_by', '!=', 'null')->pluck('resolved_by');

            foreach ($resolved_by as $name)
            {
                if (array_key_exists($name, $data))
                {
                    $data[$name]++;
                }
                else
                {
                    $data[$name] = 1;
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'user_count'    => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get resolved by count for Security incidents.',
            ];
        }

        return response()->json($response);
    }
}
