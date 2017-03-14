<?php

namespace App\Http\Controllers;

use App\SecurityCenter\SecurityCenterAssetVuln;
use App\SecurityCenter\SecurityCenterCritical;
use App\SecurityCenter\SecurityCenterHigh;
use App\SecurityCenter\SecurityCenterMedium;
use App\SecurityCenter\SecurityCenterSeveritySummary;
use Carbon\Carbon;
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

    /************************************************
     * SecurityCenter asset vulnerability functions *
     ************************************************/

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
            $regex = '/([A-Z][A-Z][A-Z] - .+)/';

            $asset_vulns = SecurityCenterAssetVuln::orderBy('asset_name', 'asc')->get();

            foreach ($asset_vulns as $asset) {
                if (preg_match($regex, $asset['asset_name'])) {
                    $data[] = \Metaclassing\Utility::decodeJson($asset['data']);
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
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

    /**
     * Get SecurityCenter asset vulnerabilities by asset score.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAssetVulnsByScore()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $regex = '/([A-Z][A-Z][A-Z] - .+)/';

            $asset_vulns = SecurityCenterAssetVuln::orderBy('asset_score', 'desc')->select('asset_name', 'asset_score')->get();

            foreach ($asset_vulns as $asset) {
                if (preg_match($regex, $asset['asset_name'])) {
                    //$data[] = \Metaclassing\Utility::decodeJson($asset['data']);
                    $data[] = [
                        'asset_name'    => $asset['asset_name'],
                        'asset_score'   => $asset['asset_score'],
                    ];
                }
            }

            $response = [
                'success'       => true,
                'count'         => count($data),
                'asset_vulns'   => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get asset vulnerabilities.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter asset vulnerabilities by asset name.
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
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get asset vulnerabilities for asset: '.$asset,
            ];
        }

        return response()->json($response);
    }

    /******************************************
     * SecurityCenter vulnerability functions *
     ******************************************/

    /**
     * Get count of SecurityCenter vulnerabilities for each severity (critical, high, medium).
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnerabilityCounts()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $one_week = Carbon::now()->subDays(7)->toDateTimeString();

            $severity_sums = SecurityCenterSeveritySummary::select('severity_name', 'severity_count')->get();

            foreach ($severity_sums as $sum) {
                if (strcmp($sum['severity_name'], 'Low') != 0 && strcmp($sum['severity_name'], 'Info') != 0) {
                    $data[$sum['severity_name']] = $sum['severity_count'];
                }
            }

            $data['Total'] = $data['Critical'] + $data['High'] + $data['Medium'];

            $response = [
                'success'               => true,
                'vulnerability_counts'  => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get vulnerability severity counts',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get top ten most vulnerable hosts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTopTenVulnerableHosts()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];
            $crticial_hosts = [];
            $top_ten = [];
            $top_ten_hosts = [];

            // get critical and high vulnerabilities
            $critical_vulns = SecurityCenterCritical::where([
                ['has_been_mitigated', '=', 0],
                ['exploit_available', '=', 'Yes'],
            ])->select('dns_name', 'ip_address')->get();

            /*
            $high_vulns = SecurityCenterHigh::where([
                ['has_been_mitigated', '=', 0],
                ['exploit_available', '=', 'Yes'],
            ])->select('dns_name', 'ip_address')->get();
            */

            // cycle through critical vulnerabilities and build counts for each host
            foreach ($critical_vulns as $vuln) {
                if (array_key_exists($vuln['dns_name'], $data)) {
                    $data[$vuln['dns_name']]['count']++;
                } else {
                    $data[$vuln['dns_name']]['count'] = 1;
                }

                // include host ip address
                $data[$vuln['dns_name']]['ip_address'] = $vuln['ip_address'];
            }

            // get the keys
            $keys = array_keys($data);

            // cycle through the keys and build the critical hosts array
            foreach ($keys as $key) {
                $critical_hosts[] = [
                    'hostname'          => $key,
                    'critical_count'    => $data[$key]['count'],
                ];
            }

            // sort that sucker by critical count
            uasort($critical_hosts, function ($a, $b) {
                return $b['critical_count'] <=> $a['critical_count'];
            });

            // gimme the first 10 hosts
            $top_ten = array_slice($critical_hosts, 0, 10, true);

            // rebuild sorted top ten hosts array
            foreach ($top_ten as $host) {
                $top_ten_hosts[] = [
                    'hostname'          => $host['hostname'],
                    'critical_count'    => $host['critical_count'],
                    'ip_address'        => $data[$host['hostname']]['ip_address'],
                ];
            }

            // respond
            $response = [
                'success'       => true,
                'count'         => count($top_ten_hosts),
                'top_ten_hosts' => $top_ten_hosts,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get top ten most vulnerable hosts.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter vulnerabilities for a particular severity.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnerabilities($severity)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            switch ($severity) {
                case 'critical':
                    $vulns = SecurityCenterCritical::where('has_been_mitigated', '=', 0)->paginate(1000);
                    break;

                case 'high':
                    $vulns = SecurityCenterHigh::where('has_been_mitigated', '=', 0)->paginate(1000);
                    break;

                case 'medium':
                    $vulns = SecurityCenterMedium::where('has_been_mitigated', '=', 0)->paginate(1000);
                    break;

                default:
                    throw new \Exception();
            }

            foreach ($vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $vulns->total(),
                'count'             => $vulns->count(),
                'current_page'      => $vulns->currentPage(),
                'next_page_url'     => $vulns->nextPageUrl(),
                'has_more_pages'    => $vulns->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get SecurityCenter vulnerabilities for severity: '.$severity,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter vulnerabilities of a particular severity for a particular device by device name.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnsByDevice($severity, $device)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            switch ($severity) {
                case 'critical':
                    $vulns = SecurityCenterCritical::where([
                        ['has_been_mitigated', '=', 0],
                        ['dns_name', 'like', $device.'%'],
                    ])->paginate(100);
                    break;

                case 'high':
                    $vulns = SecurityCenterHigh::where([
                        ['has_been_mitigated', '=', 0],
                        ['dns_name', 'like', $device.'%'],
                    ])->paginate(100);
                    break;

                case 'medium':
                    $vulns = SecurityCenterMedium::where([
                        ['has_been_mitigated', '=', 0],
                        ['dns_name', 'like', $device.'%'],
                    ])->paginate(100);
                    break;

                case 'all':
                    $data = $this->getAllVulnsForDevice($device);

                    $response = [
                        'success'   => true,
                        'total'     => count($data),
                        'vulns'     => $data,
                    ];

                    return response()->json($response);

                default:
                    throw new \Exception();
            }

            foreach ($vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $vulns->total(),
                'count'             => $vulns->count(),
                'current_page'      => $vulns->currentPage(),
                'next_page_url'     => $vulns->nextPageUrl(),
                'has_more_pages'    => $vulns->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get '.$severity.' vulnerabilities for device: '.$device,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter vulnerabilities of a particular severity for a particular device by IP.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnsByIP($severity, $ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            switch ($severity) {
                case 'critical':
                    $vulns = SecurityCenterCritical::where([
                        ['has_been_mitigated', '=', 0],
                        ['ip_address', '=', $ip],
                    ])->paginate(100);
                    break;

                case 'high':
                    $vulns = SecurityCenterHigh::where([
                        ['has_been_mitigated', '=', 0],
                        ['ip_address', '=', $ip],
                    ])->paginate(100);
                    break;

                case 'medium':
                    $vulns = SecurityCenterMedium::where([
                        ['has_been_mitigated', '=', 0],
                        ['ip_address', '=', $ip],
                    ])->paginate(100);
                    break;

                case 'all':
                    $data = $this->getAllVulnsForIP($ip);

                    $response = [
                        'success'   => true,
                        'total'     => count($data),
                        'vulns'     => $data,
                    ];

                    return response()->json($response);

                default:
                    throw new \Exception();
            }

            foreach ($vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $vulns->total(),
                'count'             => $vulns->count(),
                'current_page'      => $vulns->currentPage(),
                'next_page_url'     => $vulns->nextPageUrl(),
                'has_more_pages'    => $vulns->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get '.$severity.' vulnerabilities for ip: '.$ip,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get all SecurityCenter vulnerabilities for a particular device by device name.
     *
     * @return array
     */
    public function getAllVulnsForDevice($device)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $critical_vulns = SecurityCenterCritical::where([
                    ['has_been_mitigated', '=', 0],
                    ['dns_name', 'like', $device.'%'],
                ])->get();

            $high_vulns = SecurityCenterHigh::where([
                    ['has_been_mitigated', '=', 0],
                    ['dns_name', 'like', $device.'%'],
                ])->get();

            $medium_vulns = SecurityCenterMedium::where([
                    ['has_been_mitigated', '=', 0],
                    ['dns_name', 'like', $device.'%'],
                ])->get();

            foreach ($critical_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($high_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($medium_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }
        } catch (\Exception $e) {
            $data = [
                'success'   => false,
                'message'   => 'Failed to get vulnerabilities for device: '.$device,
            ];
        }

        return $data;
    }

    /**
     * Get all SecurityCenter vulnerabilities for a particular device by IP.
     *
     * @return array
     */
    public function getAllVulnsForIP($ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $critical_vulns = SecurityCenterCritical::where([
                    ['has_been_mitigated', '=', 0],
                    ['ip_address', '=', $ip],
                ])->get();

            $high_vulns = SecurityCenterHigh::where([
                    ['has_been_mitigated', '=', 0],
                    ['ip_address', '=', $ip],
                ])->get();

            $medium_vulns = SecurityCenterMedium::where([
                    ['has_been_mitigated', '=', 0],
                    ['ip_address', '=', $ip],
                ])->get();

            foreach ($critical_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($high_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($medium_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }
        } catch (\Exception $e) {
            $data = [
                'success'   => false,
                'message'   => 'Failed to get vulnerabilities for ip: '.$ip,
            ];
        }

        return $data;
    }

    /**
     * Get SecurityCenter vulnerabilities of a particular severity
     * (or all severities) that have exploits available.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnsWithExploit($severity)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            switch ($severity) {
                case 'critical':
                    $vulns = SecurityCenterCritical::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                    ])->paginate(100);
                    break;

                case 'high':
                    $vulns = SecurityCenterHigh::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                    ])->paginate(100);
                    break;

                case 'medium':
                    $vulns = SecurityCenterMedium::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                    ])->paginate(100);
                    break;

                /*
                case 'all':
                    $data = $this->getAllVulnsWithExploit();

                    $response = [
                        'success'   => true,
                        'total'     => count($data),
                        'vulns'     => $data,
                    ];

                    return response()->json($response);
                /**/

                default:
                    throw new \Exception();
            }

            foreach ($vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $vulns->total(),
                'count'             => $vulns->count(),
                'current_page'      => $vulns->currentPage(),
                'next_page_url'     => $vulns->nextPageUrl(),
                'has_more_pages'    => $vulns->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get '.$severity.' vulnerabilities with exploits available.',
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter vulnerabilities of a particular severity (or all severities) that have
     * exploits available, for a particular device by device name.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnsWithExploitForDevice($severity, $device)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            switch ($severity) {
                case 'critical':
                    $vulns = SecurityCenterCritical::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                        ['dns_name', 'like', $device.'%'],
                    ])->paginate(100);
                    break;

                case 'high':
                    $vulns = SecurityCenterHigh::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                        ['dns_name', 'like', $device.'%'],
                    ])->paginate(100);
                    break;

                case 'medium':
                    $vulns = SecurityCenterMedium::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                        ['dns_name', 'like', $device.'%'],
                    ])->paginate(100);
                    break;

                case 'all':
                    $data = $this->getAllVulnsWithExploitForDevice($device);

                    $response = [
                        'success'   => true,
                        'total'     => count($data),
                        'vulns'     => $data,
                    ];

                    return response()->json($response);

                default:
                    throw new \Exception();
            }

            foreach ($vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $vulns->total(),
                'count'             => $vulns->count(),
                'current_page'      => $vulns->currentPage(),
                'next_page_url'     => $vulns->nextPageUrl(),
                'has_more_pages'    => $vulns->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get '.$severity.' vulnerabilities with exploits available for device: '.$device,
            ];
        }

        return response()->json($response);
    }

    /**
     * Get SecurityCenter vulnerabilities of a particular severity (or all severities) that have
     * exploits available, for a particular device by IP.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVulnsWithExploitForIP($severity, $ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            switch ($severity) {
                case 'critical':
                    $vulns = SecurityCenterCritical::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                        ['ip_address', '=', $ip],
                    ])->paginate(100);
                    break;

                case 'high':
                    $vulns = SecurityCenterHigh::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                        ['ip_address', '=', $ip],
                    ])->paginate(100);
                    break;

                case 'medium':
                    $vulns = SecurityCenterMedium::where([
                        ['has_been_mitigated', '=', 0],
                        ['exploit_available', '=', 'Yes'],
                        ['ip_address', '=', $ip],
                    ])->paginate(100);
                    break;

                case 'all':
                    $data = $this->getAllVulnsWithExploitForIP($ip);

                    $response = [
                        'success'   => true,
                        'total'     => count($data),
                        'vulns'     => $data,
                    ];

                    return response()->json($response);

                default:
                    throw new \Exception();
            }

            foreach ($vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            $response = [
                'success'           => true,
                'total'             => $vulns->total(),
                'count'             => $vulns->count(),
                'current_page'      => $vulns->currentPage(),
                'next_page_url'     => $vulns->nextPageUrl(),
                'has_more_pages'    => $vulns->hasMorePages(),
                'vulns'             => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'   => false,
                'message'   => 'Failed to get '.$severity.' vulnerabilities with exploits available for IP: '.$ip,
            ];
        }

        return response()->json($response);
    }

    /**
     * HELPER FUNCTION: Get all SecurityCenter vulnerabilities with an exploit available.
     *
     * @return array
     */
    public function getAllVulnsWithExploit()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $critical_vulns = SecurityCenterCritical::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                ])->get();

            $high_vulns = SecurityCenterHigh::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                ])->get();

            $medium_vulns = SecurityCenterMedium::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                ])->get();

            foreach ($critical_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($high_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($medium_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }
        } catch (\Exception $e) {
            $data = [
                'success'   => false,
                'message'   => 'Failed to get vulnerabilities with exploits available.',
            ];
        }

        return $data;
    }

    /**
     * HELPER FUNCTION: Get all SecurityCenter vulnerabilities with an exploit available
     * for a particular device, by device name.
     *
     * @return array
     */
    public function getAllVulnsWithExploitForDevice($device)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $critical_vulns = SecurityCenterCritical::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                    ['dns_name', 'like', $device.'%'],
                ])->get();

            $high_vulns = SecurityCenterHigh::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                    ['dns_name', 'like', $device.'%'],
                ])->get();

            $medium_vulns = SecurityCenterMedium::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                    ['dns_name', 'like', $device.'%'],
                ])->get();

            foreach ($critical_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($high_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($medium_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }
        } catch (\Exception $e) {
            $data = [
                'success'   => false,
                'message'   => 'Failed to get vulnerabilities with exploits available for device: '.$device,
            ];
        }

        return $data;
    }

    /**
     * HELPER FUNCTION: Get all SecurityCenter vulnerabilities with an exploit available
     * for a particular device, by IP.
     *
     * @return array
     */
    public function getAllVulnsWithExploitForIP($ip)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $critical_vulns = SecurityCenterCritical::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                    ['ip_address', '=', $ip],
                ])->get();

            $high_vulns = SecurityCenterHigh::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                    ['ip_address', '=', $ip],
                ])->get();

            $medium_vulns = SecurityCenterMedium::where([
                    ['has_been_mitigated', '=', 0],
                    ['exploit_available', '=', 'Yes'],
                    ['ip_address', '=', $ip],
                ])->get();

            foreach ($critical_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($high_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }

            foreach ($medium_vulns as $vuln) {
                $data[] = \Metaclassing\Utility::decodeJson($vuln['data']);
            }
        } catch (\Exception $e) {
            $data = [
                'success'   => false,
                'message'   => 'Failed to get vulnerabilities with exploits available for IP: '.$ip,
            ];
        }

        return $data;
    }
}
