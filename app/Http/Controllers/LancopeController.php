<?php

namespace App\Http\Controllers;

use App\Lancope\InsideHostTrafficSnapshot;
use Tymon\JWTAuth\Facades\JWTAuth;

class LancopeController extends Controller
{
    /**
     * Create new Lancope controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get aggregate statistics on inside host traffic.
     *
     * @return \Illuminate\Http\Response
     */
    public function getInsideHostTrafficAggregates()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $traffic_stats = [];
            $app_name_regex = '/(.+) \(unclassified\)/';

            $snapshots = InsideHostTrafficSnapshot::select('application_name', 'traffic_outbound_Bps', 'traffic_inbound_Bps', 'traffic_within_Bps')->get();

            foreach ($snapshots as $snapshot) {
                if (preg_match($app_name_regex, $snapshot['application_name'], $hits)) {
                    $app_name = $hits[1];
                } else {
                    $app_name = $snapshot['application_name'];
                }

                if (array_key_exists($app_name, $data)) {
                    $data[$app_name]['outbound_traffic'] += $snapshot['traffic_outbound_Bps'];
                    $data[$app_name]['inbound_traffic'] += $snapshot['traffic_inbound_Bps'];
                    $data[$app_name]['internal_traffic'] += $snapshot['traffic_within_Bps'];
                } else {
                    $data[$app_name] = [
                        'outbound_traffic'    => $snapshot['traffic_outbound_Bps'],
                        'inbound_traffic'     => $snapshot['traffic_inbound_Bps'],
                        'internal_traffic'    => $snapshot['traffic_within_Bps'],
                    ];
                }
            }

            $keys = array_keys($data);

            foreach ($keys as $key) {
                $traffic_stats[] = [
                    'applicaton'          => $key,
                    'outbound_traffic'    => $data[$key]['outbound_traffic'],
                    'inbound_traffic'     => $data[$key]['inbound_traffic'],
                    'internal_traffic'    => $data[$key]['internal_traffic'],
                ];
            }

            $response = [
                'success'          => true,
                'count'            => count($traffic_stats),
                'traffic_stats'    => $traffic_stats,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get inside host traffic statistics.',
            ];
        }

        return response()->json($response);
    }
}
