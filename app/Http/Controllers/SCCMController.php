<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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


    public function uploadAllSystems(Request $request)
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$data = [];

    		$input = $request->all();

    		Log::info($input);

    		foreach($input as $attribute)
    		{
    			foreach($attribute as $key => $value)
    			{
    				Log::info('key: '.$key);
    				Log::info('value: '.$value);

    				$data[$key] = $value;
    			}
    		}

    		$this->processSCCMSystem($data);

    		$response = [
    			'success'	=> true,
    			'input'		=> $data,
    		];

    	}
    	catch (\Exception $e)
    	{
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to upload systems from SCCM dump.',
    			'exception'	=> $e,
    		];
    	}

    	return response()->json($response);
    }

    public function processSCCMSystem($system)
    {
    	//$exists = SCCMSystem::where('system_name', '=', $system['system_name'])->value('id');


    }
}
