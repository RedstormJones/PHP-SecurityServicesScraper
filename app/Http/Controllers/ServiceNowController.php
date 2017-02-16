<?php

namespace App\Http\Controllers;

require_once app_path('Console/Crawler/Crawler.php');

use App\ServiceNow\cmdbServer;
use App\ServiceNow\ServiceNowIdmIncident;
use App\ServiceNow\ServiceNowIncident;
use App\ServiceNow\ServiceNowSapRoleAuthIncident;
use Illuminate\Support\Facades\Log;
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

    /**************************************
     * Service Now CMDB server functions. *
     **************************************/

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

    /********************************************
     * Service Now incident functions. *
     ********************************************/

    /**
     * Get ServiceNow incidents by caller.
     *
     * @return void
     */
    public function getIncidentsByCaller($caller)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            // setup cookie jar
            $cookiejar = storage_path('app/cookies/servicenow_cookie.txt');

            // instantiate crawler
            $crawler = new \Crawler\Crawler($cookiejar);

            // point url to incidents table and add necessary query params
            $url = 'https://kiewit.service-now.com/api/now/v1/table/incident?sysparm_display_value=true&caller_id='.rawurlencode($caller);

            // setup HTTP headers with basic auth
            $headers = [
                'accept: application/json',
                'authorization: Basic '.getenv('SERVICENOW_AUTH'),
                'cache-control: no-cache',
            ];
            curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

            // send request and capture response
            $response = $crawler->get($url);

            // dump response to file
            file_put_contents(storage_path('app/responses/incidents.dump'), $response);

            // JSON decode response
            $incident_results = \Metaclassing\Utility::decodeJson($response);

            // grab the data we care about and tell the world how many incidents we have
            $incidents = $incident_results['result'];

            // JSON encode and dump incident collection to file
            //file_put_contents(storage_path('app/collections/incidents_collection.json'), \Metaclassing\Utility::encodeJson($incidents));

            $response = [
                'success'   => true,
                'count'     => count($incidents),
                'incidents' => $incidents,
            ];
        } catch (\Exception $e) {
            Log::info('Failed to get ServiceNow incidents for caller '.$caller.': '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get ServiceNow incidents for caller: '.$caller,
                'exception' => $e->getMessage(),
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

            foreach ($resolved_by as $name) {
                if (array_key_exists($name, $data)) {
                    $data[$name]++;
                } else {
                    $data[$name] = 1;
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'user_count'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get resolved by count for Security incidents.',
            ];
        }

        return response()->json($response);
    }

    /***************************************
     * Service Now IDM incident functions. *
     ***************************************/

    /**
     * Get all Security incidents in ServiceNow.
     *
     * @return void
     */
    public function getAllIDMIncidents()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowIdmIncident::paginate(100);

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
                'message'    => 'Failed to get IDM incidents.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get active IDM incidents in ServiceNow.
     *
     * @return void
     */
    public function getActiveIDMIncidents()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowIdmIncident::where([
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
                'message'    => 'Failed to get active IDM incidents.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get count of resolved tickets by user for IDM incidents.
     *
     * @return void
     */
    public function getResolvedByUserCount_IDM()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $resolved_by = ServiceNowIdmIncident::where('resolved_by', '!=', 'null')->pluck('resolved_by');

            foreach ($resolved_by as $name) {
                if (array_key_exists($name, $data)) {
                    $data[$name]++;
                } else {
                    $data[$name] = 1;
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'user_count'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get resolved by count for IDM incidents.',
            ];
        }

        return response()->json($response);
    }

    /*************************************************
     * Service Now SAP role auth incident functions. *
     *************************************************/

    /**
     * Get all Security incidents in ServiceNow.
     *
     * @return void
     */
    public function getAllSAPRoleAuthIncidents()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowSapRoleAuthIncident::paginate(100);

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
                'message'    => 'Failed to get SAP role auth incidents.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get active Security incidents in ServiceNow.
     *
     * @return void
     */
    public function getActiveSAPRoleAuthIncidents()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $tickets = ServiceNowSapRoleAuthIncident::where([
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
                'message'    => 'Failed to get active SAP role auth incidents.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get count of resolved tickets by user for SAP role auth incidents.
     *
     * @return void
     */
    public function getResolvedByUserCount_SAPRoleAuth()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $resolved_by = ServiceNowSapRoleAuthIncident::where('resolved_by', '!=', 'null')->pluck('resolved_by');

            foreach ($resolved_by as $name) {
                if (array_key_exists($name, $data)) {
                    $data[$name]++;
                } else {
                    $data[$name] = 1;
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'user_count'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get resolved by count for SAP role auth incidents.',
            ];
        }

        return response()->json($response);
    }
}
