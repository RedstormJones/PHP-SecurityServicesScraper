<?php

namespace App\Http\Controllers;

use App\ServiceNow\cmdbServer;
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
     * Get all CMDB servers
     *
     * @return void
     */
    public function getCMDBServers()
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$data = [];

    		$servers = cmdbServer::paginate(100);

    		foreach($servers as $server)
    		{
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
    	}
    	catch (\Exception $e) {
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to get CMDB servers.',
    		];
    	}

    	return response()->json($response);
    }


    /**
     * Get CMDB server by name
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
    			'success'	=> true,
    			'server'	=> $data,
    		];
    	}
    	catch (\Exception $e) {
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to get CMDB server: '.$name,
    		];
    	}

    	return response()->json($response);
    }

    /**
     * Get CMDB server by IP
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
    			'success'	=> true,
    			'server'	=> $data,
    		];
    	}
    	catch (\Exception $e) {
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to get CMDB server for IP: '.$ip,
    		];
    	}

    	return response()->json($response);
    }


    /**
     * Get CMDB servers by OS
     *
     * @return void
     */
    public function getCMDBServersByOS($os)
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$data = [];

    		$servers = cmdbServer::where('os', 'like', '%'.$os.'%')->get();

    		foreach($servers as $server) {
    			$data[] = \Metaclassing\Utility::decodeJson($server['data']);
    		}

    		$response = [
    			'success'	=> true,
    			'count'		=> count($data),
    			'servers'	=> $data,
    		];
    	}
    	catch (\Exception $e) {
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to get CMDB servers for operating system: '.$os,
    		];
    	}

    	return response()->json($response);
    }


    /**
     * Get CMDB servers by District
     *
     * @return void
     */
    public function getCMDBServersByDistrict($district)
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$data = [];

    		$servers = cmdbServer::where('district', 'like', $district.'%')->get();

    		if(count($servers) == 0) {
    			throw new \Exception();
    		}
    		else {
	    		foreach($servers as $server) {
	    			$data[] = \Metaclassing\Utility::decodeJson($server['data']);
	    		}

	    		$response = [
	    			'success'	=> true,
	    			'count'		=> count($data),
	    			'servers'	=> $data,
	    		];
	    	}
    	}
    	catch (\Exception $e) {
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to get CMDB servers for District: '.$district,
    		];
    	}

    	return response()->json($response);
    }
}
