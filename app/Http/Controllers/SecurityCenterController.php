<?php

namespace App\Http\Controllers;

use App\SecurityCenter\SecurityCenterAssetVuln;
use Tymon\JWTAuth\Facades\JWTAuth;


class SecurityCenterController extends Controller
{
    /**
     * Create new SecurityCenter Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get all SecurityCenter asset vulnerabilities
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllAssetVulns()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $regex = '/([A-Z][A-Z][A-Z] - .+)/';

            $asset_vulns = SecurityCenterAssetVuln::orderBy('asset_name', 'asc')->get();

            foreach($asset_vulns as $asset)
            {
                if(preg_match($regex, $asset['asset_name'])) {
                    $data[] = \Metaclassing\Utility::decodeJson($asset['data']);
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'asset_vulns'   => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get SecurityCenter asset vulnerabilities.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter asset vulnerabilities by asset score
     *
     * @return \Illuminate\Http\Response
     */
    public function getAssetVulnsByScore()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $regex = '/([A-Z][A-Z][A-Z] - .+)/';

            $asset_vulns = SecurityCenterAssetVuln::orderBy('asset_score', 'desc')->get();

            foreach($asset_vulns as $asset)
            {
                if(preg_match($regex, $asset['asset_name'])) {
                    $data[] = \Metaclassing\Utility::decodeJson($asset['data']);
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'asset_vulns'   => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get asset vulnerabilities.',
            ];
        }

        return response()->json($response);
    }


    /**
     * Get SecurityCenter asset vulnerabilities by asset name
     *
     * @return \Illuminate\Http\Response
     */
    public function getAssetVulnsByName($asset)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $asset_vuln = SecurityCenterAssetVuln::where('asset_name', 'like', $asset.'%')->firstOrFail();

            $data = \Metaclassing\Utility::decodeJson($asset_vuln['data']);

            $response = [
                'success'       => true,
                'asset_vulns'   => $data,
            ];
        }
        catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get asset vulnerabilities for asset: '.$asset,
            ];
        }

        return response()->json($response);
    }

}
