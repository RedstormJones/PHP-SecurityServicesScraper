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
     * Get all SecurityCenter asset vulnerabilities.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllAssetVulns()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $asset_vulns = SecurityCenterAssetVuln::all();

            foreach ($asset_vulns as $asset) {
                $data[] = \Metaclassing\Utility::decodeJson($asset['data']);
            }

            $response = [
                'success'       => true,
                'asset_vulns'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get SecurityCenter asset vulnerabilities.',
            ];
        }

        return response()->json($response);
    }
}
