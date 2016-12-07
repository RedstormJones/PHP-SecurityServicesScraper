<?php

namespace App\Http\Controllers;

use App\Netman\SiteSubnet;
use Tymon\JWTAuth\Facades\JWTAuth;

class NetmanSiteSubnetController extends Controller
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
     * Returns a list of all site subnets.
     *
     * @return \Illuminate\Http\Response
     */
    public function listAll()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
        	$data = [];

        	$site_subnets = SiteSubnet::paginate(100);

	        foreach ($site_subnets as $site_subnet) {
	            $data[] = \Metaclassing\Utility::decodeJson($site_subnet['data']);
	        }

            $response = [
                'success'           => true,
                'total'             => $site_subnets->total(),
                'count'             => $site_subnets->count(),
                'current_page'      => $site_subnets->currentPage(),
                'next_page_url'     => $site_subnets->nextPageUrl(),
                'has_more_pages'	=> $site_subnets->hasMorePages(),
                'site_subnets'    	=> $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get list of job site subnets',
            ];
        }

        return response()->json($response);
    }

    /**
     * Queries for a particular site subnet by base IP.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSiteSubnetByIP($ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $site_subnet = SiteSubnet::where('ip_address', '=', $ip)->pluck('data');

            foreach ($site_subnet as $site) {
                $data[] = \Metaclassing\Utility::decodeJson($site);
            }

            $response = [
                'success'          => true,
                'total'            => count($data),
                'site_subnets'     => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Could not find a site subnet matching the provided base IP',
            ];
        }

        return response()->json($response);
    }

    /**
     * Queries for all site subnets with a particular netmask.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSiteSubnetsByNetmask($netmask)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $site_subnets = SiteSubnet::where('netmask', '=', $netmask)->orderBy('site')->pluck('data');

            foreach ($site_subnets as $site_subnet) {
                $data[] = \Metaclassing\Utility::decodeJson($site_subnet);
            }

            $response = [
                'success'          => true,
                'total'            => count($data),
                'site_subnets'     => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to find any site subnets with a netmask matching '.$netmask,
            ];
        }

        return response()->json($response);
    }

    /**
     * Queries for all site subnets assigned to a particular job site.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSiteSubnetsBySite($site)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $site_subnets = SiteSubnet::where('site', '=', $site)->orderBy('ip_address')->pluck('data');

            foreach ($site_subnets as $site_subnet) {
                $data[] = \Metaclassing\Utility::decodeJson($site_subnet);
            }

            $response = [
                'success'          => true,
                'total'            => count($data),
                'site_subnets'     => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to find any site subnets with a job site ID matching '.$site,
            ];
        }

        return response()->json($response);
    }
}
