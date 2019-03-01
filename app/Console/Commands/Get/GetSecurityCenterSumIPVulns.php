<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\SecurityCenter\SecurityCenterSumIpVulns;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSecurityCenterSumIPVulns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:sumipvulns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Security Center vulnerabilities by IP';

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
         * [1] Get Security Center IP summary vulnerabilities
         */

        Log::info(PHP_EOL.PHP_EOL.'***********************************************************'.PHP_EOL.'* Starting SecurityCenter sum IP vulnerabilities crawler! *'.PHP_EOL.'***********************************************************');

        $collection = [];
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

        $url = getenv('SECURITYCENTER_URL').'/rest/analysis';

        $count = 0;

        do {
            $post = [
                'type'          => 'vuln',
                'sourceType'    => 'cumulative',
                'query'         => [
                    'tool'          => 'sumip',
                    'type'          => 'vuln',
                    'startOffset'   => $count,
                    'endOffset'     => $count + 1000,
                ],
            ];

            // send request for resource, capture response and dump to file
            $response = $crawler->post($url, $url, \Metaclassing\Utility::encodeJson($post));
            file_put_contents($response_path.'sc_sumip_vulns.dump', $response);

            // JSON decode response
            $resp = \Metaclassing\Utility::decodeJson($response);

            // extract vulnerability results and add to collection
            $total_records = $resp['response']['totalRecords'];
            $returned_records = $resp['response']['returnedRecords'];

            $collection[] = $resp['response']['results'];

            Log::info('collected '.$returned_records.' sumip vulnerabilities');
            $count += $returned_records;
        } while ($count < $total_records);

        // cycle through collection and build a simple array
        $sumipvulns = [];
        foreach ($collection as $result) {
            foreach ($result as $vuln) {
                $sumipvulns[] = $vuln;
            }
        }

        // cycle through simply array and flatten array structure
        $sumip_vulns = [];

        foreach ($sumipvulns as $vuln) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            $repository_id = $vuln['repository']['id'];
            $repository_name = $vuln['repository']['name'];
            $repository_desc = $vuln['repository']['description'];

            $last_unauth_run = str_replace(' ', 'T', Carbon::createFromTimestamp(intval($vuln['lastUnauthRun'])));
            $last_auth_run = str_replace(' ', 'T', Carbon::createFromTimestamp(intval($vuln['lastAuthRun'])));

            $sumip_vulns[] = [
                'date_added'                => $date_added,
                'repository_id'             => $repository_id,
                'repository_name'           => $repository_name,
                'repository_description'    => $repository_desc,
                'lastUnauthRun'             => $last_unauth_run,
                'lastAuthRun'               => $last_auth_run,
                'osCPE'                     => $vuln['osCPE'],
                'dnsName'                   => $vuln['dnsName'],
                'ip'                        => $vuln['ip'],
                'severityInfo'              => $vuln['severityInfo'],
                'severityLow'               => $vuln['severityLow'],
                'severityMedium'            => $vuln['severityMedium'],
                'severityHigh'              => $vuln['severityHigh'],
                'severityCritical'          => $vuln['severityCritical'],
                'netbiosName'               => $vuln['netbiosName'],
                'score'                     => $vuln['score'],
                'total'                     => $vuln['total'],
                'biosGUID'                  => $vuln['biosGUID'],
                'macAddress'                => $vuln['macAddress'],
                'mcafeeGUID'                => $vuln['mcafeeGUID'],
                'tpmID'                     => $vuln['tpmID'],
                'policyName'                => $vuln['policyName'],
                'pluginSet'                 => $vuln['pluginSet'],
            ];
        }
        Log::info('total sumip vulnerability records: '.count($sumip_vulns));

        file_put_contents(storage_path('app/collections/sc_sumip_vulns.json'), \Metaclassing\Utility::encodeJson($sumip_vulns));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($sumip_vulns as $vuln) {
            $result = $producer->send([
                [
                    'topic' => 'securitycenter_ipvulns_summary',
                    'value' => \Metaclassing\Utility::encodeJson($vuln),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$vuln['dnsName']);
            }
        }

        Log::info('* Completed SecurityCenter IP summary vulnerabilities! *');
    }

    /**
     * Delete old Security Center IP summary vulnerabilities.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subDays(1)->toDateString();

        $sumip_vulns = SecurityCenterSumIpVulns::all();

        foreach ($sumip_vulns as $vuln) {
            $updated_at = substr($vuln->updated_at, 0, -9);

            if ($updated_at < $delete_date) {
                Log::info('deleting sumip vulnerability for '.$vuln->dns_name.' - '.$vuln->ip_address.' (last updated '.$updated_at.')');
                $vuln->delete();
            }
        }
    }
}
