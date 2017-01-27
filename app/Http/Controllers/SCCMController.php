<?php

namespace App\Http\Controllers;

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
     * @return \Illuminate\Http\Response
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
            $exit_code = Artisan::call('get:sccmsystems');

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
}
