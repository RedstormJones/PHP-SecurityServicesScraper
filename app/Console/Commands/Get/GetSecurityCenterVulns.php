<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\SecurityCenter\SecurityCenterCritical;
use App\SecurityCenter\SecurityCenterHigh;
use App\SecurityCenter\SecurityCenterMedium;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSecurityCenterVulns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:securitycentervulns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new critical, high and medium SecurityCenter vulnerabilities';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /*
         * [1] Get critical, high and medium vulnerabilities
         */

        Log::info(PHP_EOL.PHP_EOL.'****************************************************'.PHP_EOL.'* Starting SecurityCenter vulnerabilities crawler! *'.PHP_EOL.'****************************************************');

        $response_path = storage_path('app/responses/');

        // setup cookie jar to store cookies
        $cookiejar = storage_path('app/cookies/securitycenter_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        // build post assoc array using authentication info
        $post = [
            'username' => getenv('SECURITYCENTER_USERNAME'),
            'password' => getenv('SECURITYCENTER_PASSWORD'),
        ];

        // set url to the token resource
        $url = getenv('SECURITYCENTER_URL').'/rest/token';

        // post authentication data, capture response and dump to file
        $response = $crawler->post($url, '', $post);
        file_put_contents($response_path.'SC_login.dump', $response);

        // JSON decode response
        $resp = \Metaclassing\Utility::decodeJson($response);

        // extract token value from response and echo to console
        $token = $resp['response']['token'];

        // setup HTTP headers with token value
        $headers = [
            'X-SecurityCenter: '.$token,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // get them vulnerabilities, or the important ones at least
        $critical_collection = $this->getVulnsBySeverity($crawler, 4);  // get critical severity vulnerabilities
        $high_collection = $this->getVulnsBySeverity($crawler, 3);      // get high severity vulnerabilities
        $medium_collection = $this->getVulnsBySeverity($crawler, 2);    // get medium severity vulnerabilities
        $low_collection = $this->getVulnsBySeverity($crawler, 1);       // get low severity vulnerabilities
        $info_collection = $this->getVulnsBySeverity($crawler, 0);      // get info vulnerabilities

        // instantiate severity arrays
        $critical_vulns = [];
        $high_vulns = [];
        $medium_vulns = [];
        $low_vulns = [];
        $info_vulns = [];

        // cycle through critical vulnerabilities and build simple array
        foreach ($critical_collection as $result) {
            foreach ($result as $vuln) {
                $critical_vulns[] = $vuln;
            }
        }

        // cycle through high vulnerabilities and build simple array
        foreach ($high_collection as $result) {
            foreach ($result as $vuln) {
                $high_vulns[] = $vuln;
            }
        }

        // cycle through medium vulnerabilities and build simple array
        foreach ($medium_collection as $result) {
            foreach ($result as $vuln) {
                $medium_vulns[] = $vuln;
            }
        }

        // cycle through low vulnerabilities and build simple array
        foreach ($low_collection as $result) {
            foreach ($result as $vuln) {
                $low_vulns[] = $vuln;
            }
        }

        // cycle through info vulnerabilites and build simple array
        foreach ($info_collection as $result) {
            foreach ($result as $vuln) {
                $info_vulns[] = $vuln;
            }
        }

        $criticalvulns = [];
        $highvulns = [];
        $mediumvulns = [];
        $lowvulns = [];
        $infovulns = [];

        foreach ($critical_vulns as $vuln) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            // extract timestamp values that we care about and convert them to datetimes
            $first_seen = Carbon::createFromTimestamp($vuln['firstSeen']);
            $last_seen = Carbon::createFromTimestamp($vuln['lastSeen']);

            $first_seen_pieces = explode(' ', $first_seen);
            $firstseen = $first_seen_pieces[0].'T'.$first_seen_pieces[1];

            $last_seen_pieces = explode(' ', $last_seen);
            $lastseen = $last_seen_pieces[0].'T'.$last_seen_pieces[1];

            // if vulnPubDate or patchPubDate equals -1 then just set it to null - otherwise convert timestamp to datetime
            if ($vuln['vulnPubDate'] == '-1') {
                $vuln_pub_date = null;
            } else {
                $vuln_pub_date = Carbon::createFromTimestamp($vuln['vulnPubDate']);
                $vuln_pub_date_pieces = explode(' ', $vuln_pub_date);
                $vuln_pub_date = $vuln_pub_date_pieces[0].'T'.$vuln_pub_date_pieces[1];
            }

            if ($vuln['patchPubDate'] == '-1') {
                $patch_pub_date = null;
            } else {
                $patch_pub_date = Carbon::createFromTimestamp($vuln['patchPubDate']);
                $patch_pub_date_pieces = explode(' ', $patch_pub_date);
                $patch_pub_date = $patch_pub_date_pieces[0].'T'.$patch_pub_date_pieces[1];
            }

            if ($vuln['pluginPubDate'] == '-1') {
                $plugin_pub_date = null;
            } else {
                $plugin_pub_date = Carbon::createFromTimestamp($vuln['pluginPubDate']);
                $plugin_pub_date_pieces = explode(' ', $plugin_pub_date);
                $plugin_pub_date = $plugin_pub_date_pieces[0].'T'.$plugin_pub_date_pieces[1];
            }

            if ($vuln['pluginModDate'] == '-1') {
                $plugin_mod_date = null;
            } else {
                $plugin_mod_date = Carbon::createFromTimestamp($vuln['pluginModDate']);
                $plugin_mod_date_pieces = explode(' ', $plugin_mod_date);
                $plugin_mod_date = $plugin_mod_date_pieces[0].'T'.$plugin_mod_date_pieces[1];
            }

            $criticalvulns[] = [
                'date_added'                => $date_added,
                'cpe'                       => $vuln['cpe'],
                'pluginName'                => $vuln['pluginName'],
                'cve'                       => $vuln['cve'],
                'exploitEase'               => $vuln['exploitEase'],
                'family_type'               => $vuln['family']['type'],
                'family_name'               => $vuln['family']['name'],
                'family_id'                 => $vuln['family']['id'],
                'bid'                       => $vuln['bid'],
                'temporalScore'             => $vuln['temporalScore'],
                'exploitFrameworks'         => $vuln['exploitFrameworks'],
                'synopsis'                  => $vuln['synopsis'],
                'netbiosName'               => $vuln['netbiosName'],
                'pluginPubDate'             => $plugin_pub_date,
                'checkType'                 => $vuln['checkType'],
                'pluginModDate'             => $plugin_mod_date,
                'dnsName'                   => $vuln['dnsName'],
                'repository_name'           => $vuln['repository']['name'],
                'repository_id'             => $vuln['repository']['id'],
                'repository_description'    => $vuln['repository']['description'],
                'ip'                        => $vuln['ip'],
                'acceptRisk'                => $vuln['acceptRisk'],
                'stigSeverity'              => $vuln['stigSeverity'],
                'pluginText'                => $vuln['pluginText'],
                'pluginInfo'                => $vuln['pluginInfo'],
                'recastRisk'                => $vuln['recastRisk'],
                'description'               => $vuln['description'],
                'baseScore'                 => $vuln['baseScore'],
                'seeAlso'                   => $vuln['seeAlso'],
                'firstSeen'                 => $firstseen,
                'lastSeen'                  => $lastseen,
                'hasBeenMitigated'          => $vuln['hasBeenMitigated'],
                'vulnPubDate'               => $vuln_pub_date,
                'macAddress'                => $vuln['macAddress'],
                'version'                   => $vuln['version'],
                'protocol'                  => $vuln['protocol'],
                'pluginID'                  => $vuln['pluginID'],
                'severity_id'               => $vuln['severity']['id'],
                'severity_name'             => $vuln['severity']['name'],
                'severity_description'      => $vuln['severity']['description'],
                'port'                      => $vuln['port'],
                'riskFactor'                => $vuln['riskFactor'],
                'exploitAvailable'          => $vuln['exploitAvailable'],
                'patchPubDate'              => $patch_pub_date,
                'cvssVector'                => $vuln['cvssVector'],
                'solution'                  => $vuln['solution'],
                'xref'                      => $vuln['xref'],
            ];
        }

        foreach ($high_vulns as $vuln) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            // extract timestamp values that we care about and convert them to datetimes
            $first_seen = Carbon::createFromTimestamp($vuln['firstSeen']);
            $last_seen = Carbon::createFromTimestamp($vuln['lastSeen']);

            $first_seen_pieces = explode(' ', $first_seen);
            $firstseen = $first_seen_pieces[0].'T'.$first_seen_pieces[1];

            $last_seen_pieces = explode(' ', $last_seen);
            $lastseen = $last_seen_pieces[0].'T'.$last_seen_pieces[1];

            // if vulnPubDate or patchPubDate equals -1 then just set it to null - otherwise convert timestamp to datetime
            if ($vuln['vulnPubDate'] == '-1') {
                $vuln_pub_date = null;
            } else {
                $vuln_pub_date = Carbon::createFromTimestamp($vuln['vulnPubDate']);
                $vuln_pub_date_pieces = explode(' ', $vuln_pub_date);
                $vuln_pub_date = $vuln_pub_date_pieces[0].'T'.$vuln_pub_date_pieces[1];
            }

            if ($vuln['patchPubDate'] == '-1') {
                $patch_pub_date = null;
            } else {
                $patch_pub_date = Carbon::createFromTimestamp($vuln['patchPubDate']);
                $patch_pub_date_pieces = explode(' ', $patch_pub_date);
                $patch_pub_date = $patch_pub_date_pieces[0].'T'.$patch_pub_date_pieces[1];
            }

            if ($vuln['pluginPubDate'] == '-1') {
                $plugin_pub_date = null;
            } else {
                $plugin_pub_date = Carbon::createFromTimestamp($vuln['pluginPubDate']);
                $plugin_pub_date_pieces = explode(' ', $plugin_pub_date);
                $plugin_pub_date = $plugin_pub_date_pieces[0].'T'.$plugin_pub_date_pieces[1];
            }

            if ($vuln['pluginModDate'] == '-1') {
                $plugin_mod_date = null;
            } else {
                $plugin_mod_date = Carbon::createFromTimestamp($vuln['pluginModDate']);
                $plugin_mod_date_pieces = explode(' ', $plugin_mod_date);
                $plugin_mod_date = $plugin_mod_date_pieces[0].'T'.$plugin_mod_date_pieces[1];
            }

            $highvulns[] = [
                'date_added'                => $date_added,
                'cpe'                       => $vuln['cpe'],
                'pluginName'                => $vuln['pluginName'],
                'cve'                       => $vuln['cve'],
                'exploitEase'               => $vuln['exploitEase'],
                'family_type'               => $vuln['family']['type'],
                'family_name'               => $vuln['family']['name'],
                'family_id'                 => $vuln['family']['id'],
                'bid'                       => $vuln['bid'],
                'temporalScore'             => $vuln['temporalScore'],
                'exploitFrameworks'         => $vuln['exploitFrameworks'],
                'synopsis'                  => $vuln['synopsis'],
                'netbiosName'               => $vuln['netbiosName'],
                'pluginPubDate'             => $plugin_pub_date,
                'checkType'                 => $vuln['checkType'],
                'pluginModDate'             => $plugin_mod_date,
                'dnsName'                   => $vuln['dnsName'],
                'repository_name'           => $vuln['repository']['name'],
                'repository_id'             => $vuln['repository']['id'],
                'repository_description'    => $vuln['repository']['description'],
                'ip'                        => $vuln['ip'],
                'acceptRisk'                => $vuln['acceptRisk'],
                'stigSeverity'              => $vuln['stigSeverity'],
                'pluginText'                => $vuln['pluginText'],
                'pluginInfo'                => $vuln['pluginInfo'],
                'recastRisk'                => $vuln['recastRisk'],
                'description'               => $vuln['description'],
                'baseScore'                 => $vuln['baseScore'],
                'seeAlso'                   => $vuln['seeAlso'],
                'firstSeen'                 => $firstseen,
                'lastSeen'                  => $lastseen,
                'hasBeenMitigated'          => $vuln['hasBeenMitigated'],
                'vulnPubDate'               => $vuln_pub_date,
                'macAddress'                => $vuln['macAddress'],
                'version'                   => $vuln['version'],
                'protocol'                  => $vuln['protocol'],
                'pluginID'                  => $vuln['pluginID'],
                'severity_id'               => $vuln['severity']['id'],
                'severity_name'             => $vuln['severity']['name'],
                'severity_description'      => $vuln['severity']['description'],
                'port'                      => $vuln['port'],
                'riskFactor'                => $vuln['riskFactor'],
                'exploitAvailable'          => $vuln['exploitAvailable'],
                'patchPubDate'              => $patch_pub_date,
                'cvssVector'                => $vuln['cvssVector'],
                'solution'                  => $vuln['solution'],
                'xref'                      => $vuln['xref'],
            ];
        }

        foreach ($medium_vulns as $vuln) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            // extract timestamp values that we care about and convert them to datetimes
            $first_seen = Carbon::createFromTimestamp($vuln['firstSeen']);
            $last_seen = Carbon::createFromTimestamp($vuln['lastSeen']);

            $first_seen_pieces = explode(' ', $first_seen);
            $firstseen = $first_seen_pieces[0].'T'.$first_seen_pieces[1];

            $last_seen_pieces = explode(' ', $last_seen);
            $lastseen = $last_seen_pieces[0].'T'.$last_seen_pieces[1];

            // if vulnPubDate or patchPubDate equals -1 then just set it to null - otherwise convert timestamp to datetime
            if ($vuln['vulnPubDate'] == '-1') {
                $vuln_pub_date = null;
            } else {
                $vuln_pub_date = Carbon::createFromTimestamp($vuln['vulnPubDate']);
                $vuln_pub_date_pieces = explode(' ', $vuln_pub_date);
                $vuln_pub_date = $vuln_pub_date_pieces[0].'T'.$vuln_pub_date_pieces[1];
            }

            if ($vuln['patchPubDate'] == '-1') {
                $patch_pub_date = null;
            } else {
                $patch_pub_date = Carbon::createFromTimestamp($vuln['patchPubDate']);
                $patch_pub_date_pieces = explode(' ', $patch_pub_date);
                $patch_pub_date = $patch_pub_date_pieces[0].'T'.$patch_pub_date_pieces[1];
            }

            if ($vuln['pluginPubDate'] == '-1') {
                $plugin_pub_date = null;
            } else {
                $plugin_pub_date = Carbon::createFromTimestamp($vuln['pluginPubDate']);
                $plugin_pub_date_pieces = explode(' ', $plugin_pub_date);
                $plugin_pub_date = $plugin_pub_date_pieces[0].'T'.$plugin_pub_date_pieces[1];
            }

            if ($vuln['pluginModDate'] == '-1') {
                $plugin_mod_date = null;
            } else {
                $plugin_mod_date = Carbon::createFromTimestamp($vuln['pluginModDate']);
                $plugin_mod_date_pieces = explode(' ', $plugin_mod_date);
                $plugin_mod_date = $plugin_mod_date_pieces[0].'T'.$plugin_mod_date_pieces[1];
            }

            $mediumvulns[] = [
                'date_added'                => $date_added,
                'cpe'                       => $vuln['cpe'],
                'pluginName'                => $vuln['pluginName'],
                'cve'                       => $vuln['cve'],
                'exploitEase'               => $vuln['exploitEase'],
                'family_type'               => $vuln['family']['type'],
                'family_name'               => $vuln['family']['name'],
                'family_id'                 => $vuln['family']['id'],
                'bid'                       => $vuln['bid'],
                'temporalScore'             => $vuln['temporalScore'],
                'exploitFrameworks'         => $vuln['exploitFrameworks'],
                'synopsis'                  => $vuln['synopsis'],
                'netbiosName'               => $vuln['netbiosName'],
                'pluginPubDate'             => $plugin_pub_date,
                'checkType'                 => $vuln['checkType'],
                'pluginModDate'             => $plugin_mod_date,
                'dnsName'                   => $vuln['dnsName'],
                'repository_name'           => $vuln['repository']['name'],
                'repository_id'             => $vuln['repository']['id'],
                'repository_description'    => $vuln['repository']['description'],
                'ip'                        => $vuln['ip'],
                'acceptRisk'                => $vuln['acceptRisk'],
                'stigSeverity'              => $vuln['stigSeverity'],
                'pluginText'                => $vuln['pluginText'],
                'pluginInfo'                => $vuln['pluginInfo'],
                'recastRisk'                => $vuln['recastRisk'],
                'description'               => $vuln['description'],
                'baseScore'                 => $vuln['baseScore'],
                'seeAlso'                   => $vuln['seeAlso'],
                'firstSeen'                 => $firstseen,
                'lastSeen'                  => $lastseen,
                'hasBeenMitigated'          => $vuln['hasBeenMitigated'],
                'vulnPubDate'               => $vuln_pub_date,
                'macAddress'                => $vuln['macAddress'],
                'version'                   => $vuln['version'],
                'protocol'                  => $vuln['protocol'],
                'pluginID'                  => $vuln['pluginID'],
                'severity_id'               => $vuln['severity']['id'],
                'severity_name'             => $vuln['severity']['name'],
                'severity_description'      => $vuln['severity']['description'],
                'port'                      => $vuln['port'],
                'riskFactor'                => $vuln['riskFactor'],
                'exploitAvailable'          => $vuln['exploitAvailable'],
                'patchPubDate'              => $patch_pub_date,
                'cvssVector'                => $vuln['cvssVector'],
                'solution'                  => $vuln['solution'],
                'xref'                      => $vuln['xref'],
            ];
        }

        foreach ($low_vulns as $vuln) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            // extract timestamp values that we care about and convert them to datetimes
            $first_seen = Carbon::createFromTimestamp($vuln['firstSeen']);
            $last_seen = Carbon::createFromTimestamp($vuln['lastSeen']);

            $first_seen_pieces = explode(' ', $first_seen);
            $firstseen = $first_seen_pieces[0].'T'.$first_seen_pieces[1];

            $last_seen_pieces = explode(' ', $last_seen);
            $lastseen = $last_seen_pieces[0].'T'.$last_seen_pieces[1];

            // if vulnPubDate or patchPubDate equals -1 then just set it to null - otherwise convert timestamp to datetime
            if ($vuln['vulnPubDate'] == '-1') {
                $vuln_pub_date = null;
            } else {
                $vuln_pub_date = Carbon::createFromTimestamp($vuln['vulnPubDate']);
                $vuln_pub_date_pieces = explode(' ', $vuln_pub_date);
                $vuln_pub_date = $vuln_pub_date_pieces[0].'T'.$vuln_pub_date_pieces[1];
            }

            if ($vuln['patchPubDate'] == '-1') {
                $patch_pub_date = null;
            } else {
                $patch_pub_date = Carbon::createFromTimestamp($vuln['patchPubDate']);
                $patch_pub_date_pieces = explode(' ', $patch_pub_date);
                $patch_pub_date = $patch_pub_date_pieces[0].'T'.$patch_pub_date_pieces[1];
            }

            if ($vuln['pluginPubDate'] == '-1') {
                $plugin_pub_date = null;
            } else {
                $plugin_pub_date = Carbon::createFromTimestamp($vuln['pluginPubDate']);
                $plugin_pub_date_pieces = explode(' ', $plugin_pub_date);
                $plugin_pub_date = $plugin_pub_date_pieces[0].'T'.$plugin_pub_date_pieces[1];
            }

            if ($vuln['pluginModDate'] == '-1') {
                $plugin_mod_date = null;
            } else {
                $plugin_mod_date = Carbon::createFromTimestamp($vuln['pluginModDate']);
                $plugin_mod_date_pieces = explode(' ', $plugin_mod_date);
                $plugin_mod_date = $plugin_mod_date_pieces[0].'T'.$plugin_mod_date_pieces[1];
            }

            $lowvulns[] = [
                'date_added'                => $date_added,
                'cpe'                       => $vuln['cpe'],
                'pluginName'                => $vuln['pluginName'],
                'cve'                       => $vuln['cve'],
                'exploitEase'               => $vuln['exploitEase'],
                'family_type'               => $vuln['family']['type'],
                'family_name'               => $vuln['family']['name'],
                'family_id'                 => $vuln['family']['id'],
                'bid'                       => $vuln['bid'],
                'temporalScore'             => $vuln['temporalScore'],
                'exploitFrameworks'         => $vuln['exploitFrameworks'],
                'synopsis'                  => $vuln['synopsis'],
                'netbiosName'               => $vuln['netbiosName'],
                'pluginPubDate'             => $plugin_pub_date,
                'checkType'                 => $vuln['checkType'],
                'pluginModDate'             => $plugin_mod_date,
                'dnsName'                   => $vuln['dnsName'],
                'repository_name'           => $vuln['repository']['name'],
                'repository_id'             => $vuln['repository']['id'],
                'repository_description'    => $vuln['repository']['description'],
                'ip'                        => $vuln['ip'],
                'acceptRisk'                => $vuln['acceptRisk'],
                'stigSeverity'              => $vuln['stigSeverity'],
                'pluginText'                => $vuln['pluginText'],
                'pluginInfo'                => $vuln['pluginInfo'],
                'recastRisk'                => $vuln['recastRisk'],
                'description'               => $vuln['description'],
                'baseScore'                 => $vuln['baseScore'],
                'seeAlso'                   => $vuln['seeAlso'],
                'firstSeen'                 => $firstseen,
                'lastSeen'                  => $lastseen,
                'hasBeenMitigated'          => $vuln['hasBeenMitigated'],
                'vulnPubDate'               => $vuln_pub_date,
                'macAddress'                => $vuln['macAddress'],
                'version'                   => $vuln['version'],
                'protocol'                  => $vuln['protocol'],
                'pluginID'                  => $vuln['pluginID'],
                'severity_id'               => $vuln['severity']['id'],
                'severity_name'             => $vuln['severity']['name'],
                'severity_description'      => $vuln['severity']['description'],
                'port'                      => $vuln['port'],
                'riskFactor'                => $vuln['riskFactor'],
                'exploitAvailable'          => $vuln['exploitAvailable'],
                'patchPubDate'              => $patch_pub_date,
                'cvssVector'                => $vuln['cvssVector'],
                'solution'                  => $vuln['solution'],
                'xref'                      => $vuln['xref'],
            ];
        }

        foreach ($info_vulns as $vuln) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            // extract timestamp values that we care about and convert them to datetimes
            $first_seen = Carbon::createFromTimestamp($vuln['firstSeen']);
            $last_seen = Carbon::createFromTimestamp($vuln['lastSeen']);

            $first_seen_pieces = explode(' ', $first_seen);
            $firstseen = $first_seen_pieces[0].'T'.$first_seen_pieces[1];

            $last_seen_pieces = explode(' ', $last_seen);
            $lastseen = $last_seen_pieces[0].'T'.$last_seen_pieces[1];

            // if vulnPubDate or patchPubDate equals -1 then just set it to null - otherwise convert timestamp to datetime
            if ($vuln['vulnPubDate'] == '-1') {
                $vuln_pub_date = null;
            } else {
                $vuln_pub_date = Carbon::createFromTimestamp($vuln['vulnPubDate']);
                $vuln_pub_date_pieces = explode(' ', $vuln_pub_date);
                $vuln_pub_date = $vuln_pub_date_pieces[0].'T'.$vuln_pub_date_pieces[1];
            }

            if ($vuln['patchPubDate'] == '-1') {
                $patch_pub_date = null;
            } else {
                $patch_pub_date = Carbon::createFromTimestamp($vuln['patchPubDate']);
                $patch_pub_date_pieces = explode(' ', $patch_pub_date);
                $patch_pub_date = $patch_pub_date_pieces[0].'T'.$patch_pub_date_pieces[1];
            }

            if ($vuln['pluginPubDate'] == '-1') {
                $plugin_pub_date = null;
            } else {
                $plugin_pub_date = Carbon::createFromTimestamp($vuln['pluginPubDate']);
                $plugin_pub_date_pieces = explode(' ', $plugin_pub_date);
                $plugin_pub_date = $plugin_pub_date_pieces[0].'T'.$plugin_pub_date_pieces[1];
            }

            if ($vuln['pluginModDate'] == '-1') {
                $plugin_mod_date = null;
            } else {
                $plugin_mod_date = Carbon::createFromTimestamp($vuln['pluginModDate']);
                $plugin_mod_date_pieces = explode(' ', $plugin_mod_date);
                $plugin_mod_date = $plugin_mod_date_pieces[0].'T'.$plugin_mod_date_pieces[1];
            }

            $plugin_text_regex = '/<plugin_output>\sRemote operating system : ((.+\s)*)Confidence level : (\d+)\sMethod : (.+)/';
            if ($vuln['pluginID'] == '11936') {
                preg_match($plugin_text_regex, $vuln['pluginText'], $hits);

                $os_identification = $hits[1];
                $confidence_level = $hits[3];
                $method_used = $hits[4];
            }
            else {
                $os_identification = null;
                $confidence_level = null;
                $method_used = null;
            }

            $infovulns[] = [
                'date_added'                => $date_added,
                'cpe'                       => $vuln['cpe'],
                'pluginName'                => $vuln['pluginName'],
                'cve'                       => $vuln['cve'],
                'exploitEase'               => $vuln['exploitEase'],
                'family_type'               => $vuln['family']['type'],
                'family_name'               => $vuln['family']['name'],
                'family_id'                 => $vuln['family']['id'],
                'bid'                       => $vuln['bid'],
                'temporalScore'             => $vuln['temporalScore'],
                'exploitFrameworks'         => $vuln['exploitFrameworks'],
                'synopsis'                  => $vuln['synopsis'],
                'netbiosName'               => $vuln['netbiosName'],
                'pluginPubDate'             => $plugin_pub_date,
                'checkType'                 => $vuln['checkType'],
                'pluginModDate'             => $plugin_mod_date,
                'dnsName'                   => $vuln['dnsName'],
                'repository_name'           => $vuln['repository']['name'],
                'repository_id'             => $vuln['repository']['id'],
                'repository_description'    => $vuln['repository']['description'],
                'ip'                        => $vuln['ip'],
                'acceptRisk'                => $vuln['acceptRisk'],
                'stigSeverity'              => $vuln['stigSeverity'],
                'pluginText'                => $vuln['pluginText'],
                'pluginInfo'                => $vuln['pluginInfo'],
                'recastRisk'                => $vuln['recastRisk'],
                'description'               => $vuln['description'],
                'baseScore'                 => $vuln['baseScore'],
                'seeAlso'                   => $vuln['seeAlso'],
                'firstSeen'                 => $firstseen,
                'lastSeen'                  => $lastseen,
                'hasBeenMitigated'          => $vuln['hasBeenMitigated'],
                'vulnPubDate'               => $vuln_pub_date,
                'macAddress'                => $vuln['macAddress'],
                'version'                   => $vuln['version'],
                'protocol'                  => $vuln['protocol'],
                'pluginID'                  => $vuln['pluginID'],
                'severity_id'               => $vuln['severity']['id'],
                'severity_name'             => $vuln['severity']['name'],
                'severity_description'      => $vuln['severity']['description'],
                'port'                      => $vuln['port'],
                'riskFactor'                => $vuln['riskFactor'],
                'exploitAvailable'          => $vuln['exploitAvailable'],
                'patchPubDate'              => $patch_pub_date,
                'cvssVector'                => $vuln['cvssVector'],
                'solution'                  => $vuln['solution'],
                'xref'                      => $vuln['xref'],
                'os_identification'         => $os_identification,
                'os_confidence_level'       => $confidence_level,
                'os_identification_method'  => $method_used,
            ];
        }

        Log::info('collected '.count($criticalvulns).' critical vulnerabilities');
        Log::info('collected '.count($highvulns).' high vulnerabilities');
        Log::info('collected '.count($mediumvulns).' medium vulnerabiliites');
        Log::info('collected '.count($lowvulns).' low vulnerabilities');
        Log::info('collected '.count($infovulns).' info vulnerabilities');

        // dump vulnerability datasets to file
        file_put_contents(storage_path('app/collections/sc_criticalvulns_collection.json'), \Metaclassing\Utility::encodeJson($criticalvulns));
        file_put_contents(storage_path('app/collections/sc_highvulns_collection.json'), \Metaclassing\Utility::encodeJson($highvulns));
        file_put_contents(storage_path('app/collections/sc_medvulns_collection.json'), \Metaclassing\Utility::encodeJson($mediumvulns));
        file_put_contents(storage_path('app/collections/sc_lowvulns_collection.json'), \Metaclassing\Utility::encodeJson($lowvulns));
        file_put_contents(storage_path('app/collections/sc_infovulns_collection.json'), \Metaclassing\Utility::encodeJson($infovulns));

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($criticalvulns as $vuln) {
            $url = 'http://10.243.32.36:9200/securitycenter_vulns_critical/securitycenter_vulns_critical/';

            $post = [
                'doc'   => $vuln,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('SecurityCenter critical vuln was successfully inserted into ES: '.$response['_id']);
            } else {
                Log::error('Something went wrong inserting SecurityCenter critical vuln: '.$response['_id']);
                die('Something went wrong inserting SecurityCenter critical vuln: '.$response['_id'].PHP_EOL);
            }
        }

        foreach ($highvulns as $vuln) {
            $url = 'http://10.243.32.36:9200/securitycenter_vulns_high/securitycenter_vulns_high/';

            $post = [
                'doc'   => $vuln,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('SecurityCenter high vuln was successfully inserted into ES: '.$response['_id']);
            } else {
                Log::error('Something went wrong inserting SecurityCenter high vuln: '.$response['_id']);
                die('Something went wrong inserting SecurityCenter high vuln: '.$response['_id'].PHP_EOL);
            }
        }

        foreach ($mediumvulns as $vuln) {
            $url = 'http://10.243.32.36:9200/securitycenter_vulns_medium/securitycenter_vulns_medium/';

            $post = [
                'doc'   => $vuln,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('SecurityCenter medium vuln was successfully inserted into ES: '.$response['_id']);
            } else {
                Log::error('Something went wrong inserting SecurityCenter medium vuln: '.$response['_id']);
                die('Something went wrong inserting SecurityCenter medium vuln: '.$response['_id'].PHP_EOL);
            }
        }

        foreach ($lowvulns as $vuln) {
            $url = 'http://10.243.32.36:9200/securitycenter_vulns_low/securitycenter_vulns_low/';

            $post = [
                'doc'   => $vuln,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('SecurityCenter low vuln was successfully inserted into ES: '.$response['_id']);
            } else {
                Log::error('Something went wrong inserting SecurityCenter low vuln: '.$response['_id']);
                die('Something went wrong inserting SecurityCenter low vuln: '.$response['_id'].PHP_EOL);
            }
        }

        foreach ($infovulns as $vuln) {
            $url = 'http://10.243.32.36:9200/securitycenter_vulns_info/securitycenter_vulns_info/';

            $post = [
                'doc'   => $vuln,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('SecurityCenter info vuln was successfully inserted into ES: '.$response['_id']);
            } else {
                Log::error('Something went wrong inserting SecurityCenter info vuln: '.$response['_id']);
                die('Something went wrong inserting SecurityCenter info vuln: '.$response['_id'].PHP_EOL);
            }
        }

        Log::info('* Completed SecurityCenter critical, high, medium, low and info vulnerabilities! *');
    }

    /**
     * Function to pull vulnerability data from Security Center 5.4.0 API.
     *
     * @return array
     */
    public function getVulnsBySeverity($crawler, $severity)
    {
        Log::info('getting sev '.$severity.' vulnerabilities...');

        // point url to the resource we want
        $url = getenv('SECURITYCENTER_URL').'/rest/analysis';

        $collection = [];
        $count = 0;
        $endoffset = 10000;
        $page = 1;

        do {
            // setup post array
            $post = [
                'type'          => 'vuln',
                'sourceType'    => 'cumulative',
                'query'         => [
                    'tool'      => 'vulndetails',
                    'type'      => 'vuln',
                    'filters'   => [
                        [
                        'filterName' => 'severity',
                        'operator'   => '=',
                        'value'      => $severity,
                        ],
                    ],
                    'startOffset'   => $count,
                    'endOffset'     => $endoffset,
                ],
            ];

            // send request for resource, capture response and dump to file
            $response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));
            file_put_contents(storage_path('app/responses/SC_vulns.dump'.$page), $response);

            // JSON decode response
            $resp = \Metaclassing\Utility::decodeJson($response);

            // extract vulnerability results and add to collection
            $collection[] = $resp['response']['results'];

            // set total to the value of totalRecords in the response
            $total = $resp['response']['totalRecords'];

            // add number of returned records to count
            $count += $resp['response']['returnedRecords'];

            // add number of returned records to new count value for next endoffset
            $endoffset = $count + $resp['response']['returnedRecords'];

            // these vars may end up holding a massive amount of data per request, so set them to null at the end of each iteration
            $response = null;
            $resp = null;

            $page++;

            // wait a second before hammering the Security Center API again
            //sleep(1);
        } while ($count < $total);

        return $collection;
    }

    /**
     * Process SecurityCenter vulnerabilities into database.
     *
     * @return null
     */
    public function processVulnsBySeverity($vulns, $sev_id)
    {
        Log::info('starting vulnerability processing for severity '.$sev_id.'...');

        // cycle through vulnerabilities to create and update models
        foreach ($vulns as $vuln) {
            // switch on the provided severity id and create the corresponding new vulnerability
            switch ($sev_id) {
                case 2:
                    Log::info('creating medium severity vulnerability record for: '.$vuln['pluginName']);
                    $new_vuln = new SecurityCenterMedium();
                    break;

                case 3:
                    Log::info('creating high severity vulnerability record for: '.$vuln['pluginName']);
                    $new_vuln = new SecurityCenterHigh();
                    break;

                case 4:
                    Log::info('creating critical severity vulnerability record for: '.$vuln['pluginName']);
                    $new_vuln = new SecurityCenterCritical();
                    break;

                default:
                    Log::error('illegal severity id '.$sev_id);
                    die('Error: illegal severity id '.$sev_id);
            }

            $new_vuln->dns_name = $vuln['dnsName'];
            $new_vuln->severity_id = $vuln['severity_id'];
            $new_vuln->severity_name = $vuln['severity_name'];
            $new_vuln->risk_factor = $vuln['riskFactor'];
            $new_vuln->first_seen = $vuln['firstSeen'];
            $new_vuln->last_seen = $vuln['lastSeen'];
            $new_vuln->protocol = $vuln['protocol'];
            $new_vuln->ip_address = $vuln['ip'];
            $new_vuln->port = $vuln['port'];
            $new_vuln->mac_address = $vuln['macAddress'];
            $new_vuln->exploit_available = $vuln['exploitAvailable'];
            $new_vuln->exploit_ease = $vuln['exploitEase'];
            $new_vuln->exploit_frameworks = $vuln['exploitFrameworks'];
            $new_vuln->vuln_public_date = $vuln['vulnPubDate'];
            $new_vuln->patch_public_date = $vuln['patchPubDate'];
            $new_vuln->has_been_mitigated = $vuln['hasBeenMitigated'];
            $new_vuln->solution = $vuln['solution'];
            $new_vuln->plugin_id = $vuln['pluginID'];
            $new_vuln->plugin_name = $vuln['pluginName'];
            $new_vuln->synopsis = $vuln['synopsis'];
            $new_vuln->cpe = $vuln['cpe'];
            $new_vuln->data = \Metaclassing\Utility::encodeJson($vuln);

            $new_vuln->save();
        }

        $this->processDeletes($sev_id);
    }

    /**
     * Function to soft delete vulnerabilities older than 30 days.
     *
     * @return null
     */
    public function processDeletes($sev_id)
    {
        $delete_date = Carbon::now()->subDays(30)->toDateString();

        switch ($sev_id) {
            case 4:
                $vulns = SecurityCenterCritical::all();
                break;

            case 3:
                $vulns = SecurityCenterHigh::all();
                break;

            case 2:
                $vulns = SecurityCenterMedium::all();
                break;

            default:
                Log::error('incorrect severity id: '.$sev_id);
                break;
        }

        foreach ($vulns as $vuln) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $vuln->updated_at)->toDateString();

            if ($updated_at <= $delete_date) {
                Log::info('deleting '.$vuln->severity_name.' vulnerability: '.$vuln->plugin_id);
                $vuln->delete();
            }
        }
    }

    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];
        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.$value;
        }

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }
}
