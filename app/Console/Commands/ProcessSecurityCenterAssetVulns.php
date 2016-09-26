<?php

namespace App\Console\Commands;

use App\SecurityCenter\SecurityCenterAssetVuln;
use Illuminate\Console\Command;

class ProcessSecurityCenterAssetVulns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:securitycenterassetvulns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new asset vulnerability summary data and update model';

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
		$contents = file_get_contents(storage_path('app/collections/sc_asset_summary.json'));
		$asset_sum = \Metaclassing\Utility::decodeJson($contents);

        foreach ($asset_sum as $asset_data) {
            $asset_name = $asset_data['asset']['name'];
            $asset_id = $asset_data['asset']['id'];

            $updated = SecurityCenterAssetVuln::where('asset_id', $asset_id)->update([
                'asset_name'      => $asset_name,
                'asset_score'     => $asset_data['score'],
                'critical_vulns'  => $asset_data['severityCritical'],
                'high_vulns'      => $asset_data['severityHigh'],
                'medium_vulns'    => $asset_data['severityMedium'],
                'total_vulns'     => $asset_data['total'],
                'data'            => \Metaclassing\Utility::encodeJson($asset_data),
            ]);

            if (!$updated) {
                echo 'creating new asset vulnerability record: '.$asset_name.PHP_EOL;

                $new_asset = new SecurityCenterAssetVuln();

                $new_asset->asset_name = $asset_name;
                $new_asset->asset_id = $asset_id;
                $new_asset->asset_score = $asset_data['score'];
                $new_asset->critical_vulns = $asset_data['severityCritical'];
                $new_asset->high_vulns = $asset_data['severityHigh'];
                $new_asset->medium_vulns = $asset_data['severityMedium'];
                $new_asset->total_vulns = $asset_data['total'];
                $new_asset->data = \Metaclassing\Utility::encodeJson($asset_data);

                $new_asset->save();
            } else {
                echo 'updated asset vulnerability: '.$asset_name.PHP_EOL;
            }
        }
    }

    // end of handle()
}    // end of ProcessSecurityCenterAssetVulns command class
