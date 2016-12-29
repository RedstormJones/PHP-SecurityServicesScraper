<?php

namespace App\Console\Commands;

use App\SecurityCenter\SecurityCenterHigh;
use Illuminate\Console\Command;

class ProcessSecurityCenterHighs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:securitycenterhighs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new Security Center high vulnerability data and update database';

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
        // get critical vulnerability data and JSON decode it
        $contents = file_get_contents(storage_path('app/collections/sc_highvulns_collection.json'));
        $highvulns = \Metaclassing\Utility::decodeJson($contents);

        // setup severity array, values never change
        $severity = [
                'id'            => '3',
                'name'          => 'High',
        ];

        // cycle through vulnerabilities to create and update models
        foreach ($highvulns as $vuln) {
            // extract timestamp values that we care about and convert them to datetimes
            $firstdate = new \DateTime('@'.$vuln['firstSeen']);
            $first_seen = $firstdate->format('Y-m-d H:i:s');

            $lastdate = new \DateTime('@'.$vuln['lastSeen']);
            $last_seen = $lastdate->format('Y-m-d H:i:s');

            // if vulnPubDate or patchPubDate equals -1 then just set it to null
            // otherwise, convert timestamp to datetime
            if ($vuln['vulnPubDate'] == '-1') {
                $vuln_pub_date = null;
            } else {
                $vulnpubdate = new \DateTime('@'.$vuln['vulnPubDate']);
                $vuln_pub_date = $vulnpubdate->format('Y-m-d H:i:s');
            }

            if ($vuln['patchPubDate'] == '-1') {
                $patch_pub_date = null;
            } else {
                $patchpubdate = new \ DateTime('@'.$vuln['patchPubDate']);
                $patch_pub_date = $patchpubdate->format('Y-m-d H:i:s');
            }

            // create new high vulnerability record
            echo 'creating vulnerability record: '.$vuln['pluginName'].PHP_EOL;

            $new_vuln = new SecurityCenterHigh();

            $new_vuln->dns_name = $vuln['dnsName'];
            $new_vuln->severity_id = $severity['id'];
            $new_vuln->severity_name = $severity['name'];
            $new_vuln->risk_factor = $vuln['riskFactor'];
            $new_vuln->first_seen = $first_seen;
            $new_vuln->last_seen = $last_seen;
            $new_vuln->protocol = $vuln['protocol'];
            $new_vuln->ip_address = $vuln['ip'];
            $new_vuln->port = $vuln['port'];
            $new_vuln->mac_address = $vuln['macAddress'];
            $new_vuln->exploit_available = $vuln['exploitAvailable'];
            $new_vuln->exploit_ease = $vuln['exploitEase'];
            $new_vuln->exploit_frameworks = $vuln['exploitFrameworks'];
            $new_vuln->vuln_public_date = $vuln_pub_date;
            $new_vuln->patch_public_date = $patch_pub_date;
            $new_vuln->has_been_mitigated = $vuln['hasBeenMitigated'];
            $new_vuln->solution = $vuln['solution'];
            $new_vuln->plugin_id = $vuln['pluginID'];
            $new_vuln->plugin_name = $vuln['pluginName'];
            $new_vuln->synopsis = $vuln['synopsis'];
            $new_vuln->cpe = $vuln['cpe'];
            $new_vuln->data = \Metaclassing\Utility::encodeJson($vuln);

            $new_vuln->save();
        }

        $this->processDeletes();
    }

    // end of handle()

    /**
     * Function to soft delete vulnerabilities older than 3 months.
     *
     * @return null
     */
    public function processDeletes()
    {
        $today = new \DateTime();
        $past = $today->modify('-30 days');
        $delete_date = $past->format('Y-m-d H:i:s');

        $highvulns = SecurityCenterHigh::where('updated_at', '<=', $delete_date)->get();

        foreach ($highvulns as $vuln) {
            echo 'deleting old vulnerability: '.$vuln->plugin_id.PHP_EOL;
            $vuln->delete();
        }
    }
}    // end of ProcessSecurityCenterHighs command class
