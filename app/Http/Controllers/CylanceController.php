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
     * Show Cylance device page.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDevice($device_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $device = CylanceDevice::where('device_name', '=', $device_name)->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($device->data);

            $response = [
                'success'   => true,
                'message'   => '',
                'device'    => $data,
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

        $devices = CylanceDevice::where('last_users_text', 'like', '%'.strtoupper($user_name))->orderBy('device_created_at')->get();

        if (count($devices) > 0) {
            $data = [];

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                    'success'   => true,
                    'message'   => '',
                    'total'     => count($devices),
                    'devices'   => $data,
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Could not find any devices for user: '.$user_name,
            ];
        }

        return response()->json($response);
    }

    /**
     * Returns a list of the top 100 unsafe devices.
     *
     * @return \Illuminate\Http\Response
     */
    public function listTopUnsafeDevices()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $devices = CylanceDevice::where('files_unsafe', '>', 0)->orderBy('files_unsafe', 'desc')->get();

        if (count($devices) > 0) {
            $data = [];

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($devices),
                'devices'   => $data,
            ];
        } else {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get list of top unsafe devices',
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
        if ($district != 'KU' && strlen($district) < 3) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to find any devices belonging to '.$district,
            ];
        } else {
            $user = JWTAuth::parseToken()->authenticate();

            $devices = CylanceDevice::where('zones_text', 'like', '%'.$district.'%')->orderBy('device_created_at', 'desc')->get();

            if (count($devices) > 0) {
                $data = [];

                foreach ($devices as $device) {
                    $data[] = \Metaclassing\Utility::decodeJson($device->data);
                }

                $response = [
                    'success'   => true,
                    'message'   => '',
                    'total'     => count($devices),
                    'devices'   => $data,
                ];
            } else {
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

        $devices = CylanceDevice::where('ip_addresses_text', 'like', '%'.$ip.'%')->orderBy('device_created_at', 'desc')->get();

        if (count($devices) > 0) {
            $data = [];

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($devices),
                'devices'   => $data,
            ];
        } else {
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

        $devices = CylanceDevice::where('mac_addresses_text', 'like', '%'.$mac.'%')->orderBy('device_created_at', 'desc')->get();

        if (count($devices) > 0) {
            $data = [];

            foreach ($devices as $device) {
                $data[] = \Metaclassing\Utility::decodeJson($device->data);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($devices),
                'devices'   => $data,
            ];
        } else {
            $response = [
                'success'   => false,
                'message'   => 'Failed to find any device with a MAC address matching '.$mac,
            ];
        }

        return response()->json($response);
    }

    /*
    *   API ENDPOINTS - CYLANCE THREATS
    */

    /**
     * Returns a list of threats matching the filename provided.
     *
     * @return \Illuminate\Http\Response
     */
    public function getThreatsByName($threat_name)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $threats = CylanceThreat::where('common_name', '=', $threat_name)->get();

        if (count($threats) > 0) {
            $data = [];

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'    => true,
                'message'    => '',
                'total'      => count($threats),
                'threats'    => $data,
            ];
        } else {
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

        $threats = CylanceThreat::where('current_model', '=', 'Unsafe')
                    ->orderBy('cylance_score', 'desc')
                    ->get();

        if (count($threats) > 0) {
            $data = [];

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($threats),
                'threats'   => $data,
            ];
        } else {
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

        $threats = CylanceThreat::where('current_model', '=', $current_model)->get();

        if (count($threats) > 0) {
            $data = [];

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($threats),
                'threats'   => $data,
            ];
        } else {
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

        $threats = CylanceThreat::where('detected_by', '=', $detected_by)->get();

        if (count($threats) > 0) {
            $data = [];

            foreach ($threats as $threat) {
                $data[] = \Metaclassing\Utility::decodeJson($threat->data);
            }

            $response = [
                'success'   => true,
                'message'   => '',
                'total'     => count($threats),
                'threats'   => $data,
            ];
        } else {
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
                'message'   => '',
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
                'message'   => '',
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
