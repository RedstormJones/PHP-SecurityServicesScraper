<?php

namespace App\Http\Controllers;

use App\Events\SCCMSystemsProcessingInitiated;
use App\SCCM\SCCMSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class SCCMController extends Controller
{
    /**
     * Create a new SCCM Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Clear the SCCM all systems collection file.
     *
     * @return \Illuminate\Http\Response
     */
    public function clearSCCMSystemsUpload()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            // save off systems collection from last run
            $old_contents = file_get_contents(storage_path('app/collections/sccm_systems_collection.json'));
            file_put_contents(storage_path('app/collections/sccm_systems_collection.json.old'), $old_contents);

            // clear out the collections file
            file_put_contents(storage_path('app/collections/sccm_systems_collection.json'), '');

            $response = [
                'success'   => true,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to clear SCCM All Systems upload file.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SCCM systems from Spectre Frontend and process them into the database.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadSCCMSystems(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $input = $request->all();

            $this->enumerateSCCMSystems($input[0]);

            $response = [
                'success'      => true,
            ];
        } catch (\Exception $e) {
            Log::error('failed to upload system. Exception: '.$e);

            $response = [
                'success'      => false,
                'message'      => 'Failed to upload system from SCCM to Spectre.',
                'exception'    => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Enumerate SCCM systems into collection file.
     *
     * @return void
     */
    public function enumerateSCCMSystems($data)
    {
        Log::info('adding system: '.$data['system_name']);

        // get current SCCM systems collection and JSON decode it
        $contents = file_get_contents(storage_path('app/collections/sccm_systems_collection.json'));

        // if contents has data then decode it, otherwise instantiate an collection array
        if ($contents) {
            $system_collection = \Metaclassing\Utility::decodeJson($contents);
        } else {
            $system_collection = [];
        }

        // push new SCCM system object on to collection array
        array_push($system_collection, $data);

        // JSON encode and store new collection back to file
        file_put_contents(storage_path('app/collections/sccm_systems_collection.json'), \Metaclassing\Utility::encodeJson($system_collection));
    }

    /**
     * Programmatically execute the get:sccmsystems command.
     *
     * @return \Illuminate\Http\Response
     */
    public function processSCCMSystemsUpload()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            //$exit_code = Artisan::call('get:sccmsystems');
            event(new SCCMSystemsProcessingInitiated());

            $response = [
                'success'   => true,
                'exit_code' => $exit_code,
            ];
        } catch (\Exception $e) {
            $reponse = [
                'success'   => false,
                'message'   => 'Failed to process SCCM systems upload.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Calculate and return BitLocker compliance percentage.
     *
     * @return \Illuminate\Http\Response
     */
    public function getBitLockerCompliance()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $bitlockered = 0;

            $sccm_systems = SCCMSystem::all();
            $total = count($sccm_systems);

            foreach ($sccm_systems as $system) {
                if (strcmp($system['bitlocker_status'], 'Yes') == 0) {
                    $bitlockered++;
                }
            }

            $bitlocker_percentage = ($bitlockered / $total) * 100;

            $response = [
                'success'               => true,
                'total'                 => $total,
                'bitlocker_count'       => $bitlockered,
                'bitlocker_percentage'  => floatval(number_format($bitlocker_percentage, 2)),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get BitLocker compliance numbers: '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get BitLocker compliance numbers.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Calculate and return Cylance compliance percentage.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAVCompliance()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $cylanced = 0;
            $sceped = 0;

            $sccm_systems = SCCMSystem::all();
            $total = count($sccm_systems);

            foreach ($sccm_systems as $system) {
                if (strcmp($system['cylance_installed'], 'Yes') == 0) {
                    $cylanced++;
                } elseif (strcmp($system['scep_installed'], 'Yes') == 0) {
                    $sceped++;
                }
            }

            $cylance_percentage = ($cylanced / $total) * 100;
            $scep_percentage = ($sceped / $total) * 100;
            $av_compliance_percentage = $cylance_percentage + $scep_percentage;

            $response = [
                'success'                   => true,
                'total'                     => $total,
                'cylance_installed'         => $cylanced,
                'cylance_percentage'        => floatval(number_format($cylance_percentage, 2)),
                'scep_installed'            => $sceped,
                'scep_percentage'           => floatval(number_format($scep_percentage, 2)),
                'av_compliance_percentage'  => floatval(number_format($av_compliance_percentage, 2)),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Cylance compliance numbers: '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get Cylance compliance numbers.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Calculate and return AnyConnect compliance percentage.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAnyConnectCompliance()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $anyconnected = 0;

            $sccm_systems = SCCMSystem::all();
            $total = count($sccm_systems);

            foreach ($sccm_systems as $system) {
                if (strcmp($system['anyconnect_installed'], 'Yes') == 0) {
                    $anyconnected++;
                }
            }

            $anyconnect_percentage = ($anyconnected / $total) * 100;

            $response = [
                'success'               => true,
                'total'                 => $total,
                'anyconnect_count'      => $anyconnected,
                'anyconnect_percentage' => floatval(number_format($anyconnect_percentage, 2)),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get AnyConnect compliance numbers: '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get AnyConnect compliance numbers.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }

    /**
     * Calculate and return AnyConnect Web Security compliance percentage.
     *
     * @return \Illuminate\Http\Response
     */
    public function getWebSecurityCompliance()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $websecured = 0;

            $sccm_systems = SCCMSystem::all();
            $total = count($sccm_systems);

            foreach ($sccm_systems as $system) {
                if (strcmp($system['anyconnect_websecurity'], 'Yes') == 0) {
                    $websecured++;
                }
            }

            $websecurity_percentage = ($websecured / $total) * 100;

            $response = [
                'success'                   => true,
                'total'                     => $total,
                'websecurity_count'         => $websecured,
                'websecurity_percentage'    => floatval(number_format($websecurity_percentage, 2)),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get AnyConnect Web Security compliance numbers: '.$e);

            $response = [
                'success'   => false,
                'message'   => 'Failed to get AnyConnect Web Security compliance numbers.',
                'exception' => $e,
            ];
        }

        return response()->json($response);
    }
}
