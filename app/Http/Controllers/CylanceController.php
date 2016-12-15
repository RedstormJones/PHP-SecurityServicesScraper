<?php

namespace App\Http\Controllers;

use App\Cylance\CylanceDevice;
use App\Cylance\CylanceThreat;
use Tymon\JWTAuth\Facades\JWTAuth;

class CylanceController extends Controller
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

    /*
    *   API ENDPOINTS - CYLANCE DEVICES
    */

   /**
    * Get all Cylance devices.
    *
    * @return \Illuminate\Http\Response
    */
   public function getAllDevices()
   {
       $user = JWTAuth::parseToken()->authenticate();

       try {
           $data = [];

           $devices = CylanceDevice::paginate(1000);

           foreach ($devices as $device) {
               $data[] = \Metaclassing\Utility::decodeJson($device['data']);
           }

           $response = [
                'success'           => true,
                'total'             => $devices->total(),
                'count'             => $devices->count(),
                'page_count'        => $devices->currentPage(),
                'next_page_url'     => $devices->nextPageUrl(),
                'has_more_pages'    => $devices->hasMorePages(),
                'devices'           => $data,
            ];
       } catch (\Exception $e) {
           $response = [
                'success'   => false,
                'message'   => 'Failed to get Cylance devices.',
            ];
       }

       return response()->json($response);
   }

    /**
     * Search for a particular Cylance device.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDevice($device_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $device = CylanceDevice::where('device_name', '=', $device_name)->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($device['data']);

            $response = [
                'success'       => true,
                'message'       => '',
                'device_name'   => $device_name,
                'device'        => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Could not find any device with the name: '.$device_name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get a list of all devices where the user name provided was the last to log on.
     *
     * @return \Illuminate\Http\Response
     */
    public function listUsersDevices($user_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $devices = CylanceDevice::where('last_users_text', 'like', '%'.strtoupper($user_name))->orderBy('device_created_at')->get();

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                    'success'   => true,
                    'message'   => '',
                    'total'     => count($devices),
                    'devices'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Could not find any devices for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get the top 10 devices by quarantined files
     *
     * @return \Illuminate\Http\Response
     */
    public function getTopQuarantined()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $devices = CylanceDevice::orderBy('files_quarantined', 'desc')->take(10)->get();

            foreach($devices as $device)
            {
                $data[] = \Metaclassing\Utility::decodeJson($device['data']);
            }

            $response = [
                'success'   => true,
                'total'     => count($data),
                'devices'   => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get Cylance devices by quaraninted files count.',
            ];
        }

        return response()->json($response);
    }


    /**
     * Returns all unsafe devices.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllUnsafeDevices()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $devices = CylanceDevice::where('files_unsafe', '>', 0)->paginate(100);

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                'success'           => true,
                'total'             => $devices->total(),
                'count'             => $devices->count(),
                'current_page'      => $devices->currentPage(),
                'next_page_url'     => $devices->nextPageUrl(),
                'has_more_pages'    => $devices->hasMorePages(),
                'devices'           => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get list of top unsafe devices',
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns unsafe devices for each District.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUnsafeDevicesForDistricts()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $devices = CylanceDevice::where('files_unsafe', '>', 0)->get();

            foreach ($devices as $device) {
                if (array_key_exists($device['zones_text'], $data)) {
                    $data[$device['zones_text']]++;
                } else {
                    $data[$device['zones_text']] = 1;
                }
            }

            $keys = array_keys($data);

            $district_data = [];

            foreach ($keys as $key) {
                $district_data[] = [
                    'district_name' => $key,
                    'device_count'  => $data[$key],
                ];
            }

            $response = [
                'success'   => true,
                'count'     => count($data),
                'devices'   => $district_data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get unsafe devices for each district.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns a list of devices specific to a District.
     *
     * @return \Illuminate\Http\Response
     */
    public function listDevicesByDistrict($district)
    {
        if (strlen($district) < 3 && $district !== 'KU' && $district !== 'ku') {
            $response = [
                'success'   => false,
                'message'   => 'Failed to find any devices belonging to '.$district,
            ];
        } else {
            $user = JWTAuth::parseToken()->authenticate();

            try {
                $data = [];

                $devices = CylanceDevice::where('zones_text', 'like', '%'.$district.'%')->paginate(100);

                foreach ($devices as $device) {
                    $data[] = \Metaclassing\Utility::decodeJson($device->data);
                }

                $response = [
                    'success'           => true,
                    'total'             => $devices->total(),
                    'count'             => $devices->count(),
                    'current_page'      => $devices->currentPage(),
                    'next_page_url'     => $devices->nextPageUrl(),
                    'has_more_pages'    => $devices->hasMorePages(),
                    'devices'           => $data,
                ];
            } catch (\Exception $e) {
                $response = [
                    'success'   => false,
                    'message'   => 'Failed to find any devices belonging to '.$district,
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * Find devices that match the provided IP address.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDeviceByIP($ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $devices = CylanceDevice::where('ip_addresses_text', 'like', '%'.$ip.'%')->orderBy('device_created_at', 'desc')->get();

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                'success'   => true,
                'total'     => count($devices),
                'devices'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to find any device with an IP address matching '.$ip,
            ];
        }

        return response()->json($response);
    }

    /**
     * Find devices that match the provided MAC address.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDeviceByMAC($mac)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $devices = CylanceDevice::where('mac_addresses_text', 'like', '%'.$mac.'%')->orderBy('device_created_at', 'desc')->get();

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                'success'   => true,
                'total'     => count($devices),
                'devices'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to find any device with a MAC address matching '.$mac,
            ];
        }

        return response()->json($response);
    }



    /**
     * Get per-device average of quarantined files for each District.
     *
     * @return \Illuminate\Http\Response
     */
    public function getQuarantinedAvgsByDistrict()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $district_data = [];
            $zone = null;
            $zone_regex = '/([A-Z][A-Z][A-Z])/i';

            $devices = CylanceDevice::where('files_quarantined', '>', 0)->select('device_name', 'zones_text', 'files_quarantined')->get();

            foreach ($devices as $device)
            {
                // check that zones_text contains a three letter District code
                if(preg_match($zone_regex, $device['zones_text'], $hits))
                {
                    // check for multiple hits
                    if(count($hits) > 1)
                    {
                        /* cycle through each hit and try to find one that
                         matchs the first three letters of the device_name */
                        foreach($hits as $hit)
                        {
                            if (substr($device['device_name'], 0, 3) === $hit)
                            {
                                $zone = $hit;
                            }
                        }
                    }
                    elseif (count($hits) === 1)
                    {
                        $zone = $hits[1];
                    }


                    // if a proper zone value was found then update the data array
                    if($zone)
                    {
                        if (array_key_exists($zone, $data))
                        {
                            $data[$zone]['count']++;
                            $data[$zone]['total'] += $device['files_quarantined'];
                        }
                        else
                        {
                            $data[$zone] = [
                                'count' => 1,
                                'total' => $device['files_quarantined'],
                            ];
                        }
                    }
                }
            }

            $keys = array_keys($data);

            // cycle through the data array keys to calculate averages and build response data
            foreach ($keys as $key)
            {
                $district_avg = round(($data[$key]['total'] / $data[$key]['count']), 2);

                $district_data[] = [
                    'zone_name'         => $key,
                    'district_average'  => $district_avg,
                ];
            }

            $response = [
                'success'           => true,
                'total'             => count($district_data),
                'district_averages' => $district_data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                    'success'   => false,
                    'message'   => 'Failed to get District quarantine averages',
            ];
        }

        return response()->json($response);
    }


    public function getDeviceAgentVersions()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $agent_data = [];

            $devices = CylanceDevice::select('device_name', 'agent_version_text', 'os_versions_text', 'policy_name')->get();

            foreach ($devices as $device)
            {
                if(array_key_exists($device['agent_version_text'], $data))
                {
                    $data[$device['agent_version_text']]++;
                }
                else
                {
                    $data[$device['agent_version_text']] = 1;
                }
            }

            $keys = array_keys($data);

            foreach ($keys as $key)
            {
                $agent_data[] = [
                    'agent_version'         => $key,
                    'agent_version_count'   => $data[$key],
                ];
            }


            $response = [
                'success'               => true,
                'count'                 => count($data),
                'agent_version_counts'  => $agent_data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                    'success'   => false,
                    'message'   => 'Failed to get District quarantine averages',
            ];
        }

        return response()->json($response);
    }




    /**********************************
    *   CYLANCE THREATS - ENDPOINTS   *
    **********************************/

    /**
     * Get all Cylance threats.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllThreats()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = CylanceThreat::paginate(100);

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $threats->total(),
                'count'             => $threats->count(),
                'current_page'      => $threats->currentPage(),
                'next_page_url'     => $threats->nextPageUrl(),
                'has_more_pages'    => $threats->hasMorePages(),
                'threats'           => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get Cylance threats.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns a list of threats matching the filename provided.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatsByName($threat_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = CylanceThreat::where('common_name', '=', $threat_name)->get();

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'    => true,
                'total'      => count($threats),
                'threats'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Could not find any threats named: '.$threat_name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns a list of the top Cylance threats.
     *
     * @return \Illuminate\Http\Response
     */
    public function listTopThreats()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = CylanceThreat::where('current_model', '=', 'Unsafe')->paginate(100);

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'           => true,
                'total'             => $threats->total(),
                'count'             => $threats->count(),
                'current_page'      => $threats->currentPage(),
                'next_page_url'     => $threats->nextPageUrl(),
                'has_more_pages'    => $threats->hasMorePages(),
                'unsafe_threats'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get list of top threats',
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns a list of threats by current model.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatsByModel($current_model)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = CylanceThreat::where('current_model', '=', $current_model)->paginate(100);

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'           => true,
                'total'             => $threats->total(),
                'count'             => $threats->count(),
                'current_page'      => $threats->currentPage(),
                'next_page_url'     => $threats->nextPageUrl(),
                'has_more_pages'    => $threats->hasMorePages(),
                'unsafe_threats'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'No threats found for model: '.$current_model,
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns a list of threats detected by the provided detection mechanism.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatsByDetection($detected_by)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $threats = CylanceThreat::where('detected_by', '=', $detected_by)->paginate(100);

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'           => true,
                'total'             => $threats->total(),
                'count'             => $threats->count(),
                'current_page'      => $threats->currentPage(),
                'next_page_url'     => $threats->nextPageUrl(),
                'has_more_pages'    => $threats->hasMorePages(),
                'unsafe_threats'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'No threats found to be detected by: '.$detected_by,
            ];
        }

        return response()->json($response);
    }

    /**
     * Finds threat with an MD5 matching the MD5 provided as the function argument.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatByMD5($md5)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $threat = CylanceThreat::where('md5', '=', $md5)->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($threat->data);

            $response = [
                'success'   => true,
                'threat'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'No threats found with MD5 matching '.$md5,
            ];
        }

        return response()->json($response);
    }

    /**
     * Finds threat with a SHA256 matching the SHA256 provided as the function argument.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatBySHA256($sha256)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $threat = CylanceThreat::where('threat_id', '=', $sha256)->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($threat->data);

            $response = [
                'success'   => true,
                'threat'    => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'No threats found with SHA256 matching '.$sha256,
            ];
        }

        return response()->json($response);
    }
}    // end of CylanceController class
