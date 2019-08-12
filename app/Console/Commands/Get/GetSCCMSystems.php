<?php

namespace App\Console\Commands\Get;

use App\SCCM\SCCMSystem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSCCMSystems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:sccmsystems';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new SCCM systems upload';

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
        Log::info(PHP_EOL.PHP_EOL.'********************************'.PHP_EOL.'* Starting SCCM systems fetch! *'.PHP_EOL.'********************************');

        $sccm_systems = [];

        $sccm_data = $this->csvToArray(getenv('SCCM_ALL_SYSTEMS_FILE'), ',');

        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($sccm_data) - 1;
        Log::info('count of SCCM systems received: '.$count);

        //Use first row for names
        $labels = array_shift($sccm_data);

        foreach ($labels as $label) {
            $keys[] = $label;
        }

        Log::info('[+] building SCCM systems associative array...');

        // Bring it all together
        for ($j = 0; $j < $count; $j++) {
            $d = array_combine($keys, $sccm_data[$j]);
            $sccm_systems[$j] = $d;
        }
        Log::info('[+] ...DONE');

        $systems = [];

        Log::info('[+] normalizing SCCM systems data...');
        foreach ($sccm_systems as $system) {
            $image_date = $this->handleDate($system['ImageDate']);
            $ad_last_logon = $this->handleDate($system['ADLastLogon']);
            $ad_password_last_set = $this->handleDate($system['ADPasswordLastSet']);
            $ad_modified = $this->handleDate($system['ADModified']);
            $ad_when_changed = $this->handleDate($system['ADWhenChanged']);
            $sccm_last_heartbeat = $this->handleDate($system['SCCMLastHeartBeat']);
            $sccm_last_health_eval = $this->handleDate($system['SCCMLastHealthEval']);
            $sccm_last_hw_scan = $this->handleDate($system['SCCMLastHWScan']);
            $sccm_last_sw_scan = $this->handleDate($system['SCCMLastSWScan']);
            $sccm_policy_request = $this->handleDate($system['SCCMPolicyRequest']);

            if ($system['DeviceStatLastCheckDate']) {
                $device_stat_last_check_pieces = explode(' ', $system['DeviceStatLastCheckDate']);
                $device_stat_last_check = $device_stat_last_check_pieces[0].'T'.$device_stat_last_check_pieces[1];
            } else {
                $device_stat_last_check = null;
            }

            if ($image_date) {
                $image_date_pieces = explode(' ', $image_date);
                $image_date = $image_date_pieces[0].'T'.$image_date_pieces[1];
            }

            if ($ad_last_logon) {
                $ad_last_logon_pieces = explode(' ', $ad_last_logon);
                $ad_last_logon = $ad_last_logon_pieces[0].'T'.$ad_last_logon_pieces[1];
            }

            if ($ad_password_last_set) {
                $ad_pwd_last_set_pieces = explode(' ', $ad_password_last_set);
                $ad_password_last_set = $ad_pwd_last_set_pieces[0].'T'.$ad_pwd_last_set_pieces[1];
            }

            if ($ad_modified) {
                $ad_modified_pieces = explode(' ', $ad_modified);
                $ad_modified = $ad_modified_pieces[0].'T'.$ad_modified_pieces[1];
            }

            if ($ad_when_changed) {
                $ad_when_changed_pieces = explode(' ', $ad_when_changed);
                $ad_when_changed = $ad_when_changed_pieces[0].'T'.$ad_when_changed_pieces[1];
            }

            if ($sccm_last_heartbeat) {
                $sccm_last_hb_pieces = explode(' ', $sccm_last_heartbeat);
                $sccm_last_heartbeat = $sccm_last_hb_pieces[0].'T'.$sccm_last_hb_pieces[1];
            }

            if ($sccm_last_health_eval) {
                $sccm_last_he_pieces = explode(' ', $sccm_last_health_eval);
                $sccm_last_health_eval = $sccm_last_he_pieces[0].'T'.$sccm_last_he_pieces[1];
            }

            if ($sccm_last_hw_scan) {
                $sccm_last_hw_pieces = explode(' ', $sccm_last_hw_scan);
                $sccm_last_hw_scan = $sccm_last_hw_pieces[0].'T'.$sccm_last_hw_pieces[1];
            }

            if ($sccm_last_sw_scan) {
                $sccm_last_sw_pieces = explode(' ', $sccm_last_sw_scan);
                $sccm_last_sw_scan = $sccm_last_sw_pieces[0].'T'.$sccm_last_sw_pieces[1];
            }

            if ($sccm_policy_request) {
                $sccm_policy_request_pieces = explode(' ', $sccm_policy_request);
                $sccm_policy_request = $sccm_policy_request_pieces[0].'T'.$sccm_policy_request_pieces[1];
            }

            if ($system['DaysLastLogon']) {
                $days_since_last_logon = intval($system['DaysLastLogon']);
            } else {
                $days_since_last_logon = 0;
            }

            if (array_key_exists('Group', $system)) {
                $group = $system['Group'];
            } else {
                $group = null;
            }

            if (array_key_exists('ReportGroup', $system)) {
                $report_group = $system['ReportGroup'];
            } else {
                $report_group = null;
            }

            if (array_key_exists('SiteID', $system)) {
                $site_id = $system['SiteID'];
            } else {
                $site_id = null;
            }

            if (array_key_exists('Region', $system)) {
                $region = $system['Region'];
            } else {
                $region = null;
            }

            $systems[] = [
                'ReportDate'                => $system['ReportDate'],
                'ADModified'                => $ad_modified,
                'Group'                     => $group,
                'ADWhenChanged'             => $ad_when_changed,
                'ClientVersion'             => $system['ClientVersion'],
                'ClientStatus'              => $system['ClientStatus'],
                'OperatingSystem'           => $system['OperatingSystem'],
                'SCCMLastHeartBeat'         => $sccm_last_heartbeat,
                'SCCMPolicyRequest'         => $sccm_policy_request,
                'MetricScriptVersion'       => $system['MetricScriptVersion'],
                'Processor'                 => $system['Processor'],
                'LastLogonUserName'         => $system['LastLogonUserName'],
                'ChassisType'               => $system['ChassisType'],
                'ADPasswordLastSet'         => $ad_password_last_set,
                'SCCMManagementPoint'       => $system['SCCMManagementPoint'],
                'CylanceInstalled'          => $system['CylanceInstalled'],
                'SCEPInstalled'             => $system['SCEPInstalled'],
                'DaysLastLogon'             => $days_since_last_logon,
                'OSLanguage'                => $system['OSLanguage'],
                'PatchPercent'              => $system['PatchPercent'],
                'Owner'                     => $system['Owner'],
                'ReportGroup'               => $report_group,
                'TPM_IsOwned'               => $system['TPM_IsOwned'],
                'PatchInstalled'            => $system['PatchInstalled'],
                'SCCMLastHWScan'            => $sccm_last_hw_scan,
                'AnyConnectWebSecurity'     => $system['AnyConnectWebSecurity'],
                'IEVersion'                 => $system['IEVersion'],
                'ResourceID'                => $system['ResourceID'],
                'SerialNumber'              => $system['SerialNumber'],
                'ImageDate'                 => $image_date,
                'VideoCard'                 => $system['VideoCard'],
                'OperatingSystemVersion'    => $system['OperatingSystemVersion'],
                'COECompliant'              => $system['COECompliant'],
                'Model'                     => $system['Model'],
                'AnyConnectInstalled'       => $system['AnyConnectInstalled'],
                'SCCMLastHealthEval'        => $sccm_last_health_eval,
                'OSArch'                    => $system['OSArch'],
                'SystemName'                => $system['SystemName'],
                'TPM_IsEnabled'             => $system['TPM_IsEnabled'],
                'PatchTotal'                => $system['PatchTotal'],
                'PowerShellVersion'         => $system['PowerShellVersion'],
                'PhysicalRAM'               => $system['PhysicalRAM'],
                'Manufacturer'              => $system['Manufacturer'],
                'DeviceStatLastCheckDate'   => $device_stat_last_check,
                'DeviceStatVersion'         => $system['DeviceStatVersion'],
                'SiteID'                    => $site_id,
                'SCCMLastSWScan'            => $sccm_last_sw_scan,
                'PrimaryUsers'              => $system['PrimaryUsers'],
                'Region'                    => $region,
                'BitLockerStatus'           => $system['BitLockerStatus'],
                'Stale45Days'               => $system['Stale45Days'],
                'ADLocation'                => $system['ADLocation'],
                'District'                  => $system['District'],
                'TPM_IsActivated'           => $system['TPM_IsActivated'],
                'PatchMissing'              => $system['PatchMissing'],
                'SystemRole'                => $system['SystemRole'],
                'ImageSource'               => $system['ImageSource'],
                'ClientActivity'            => $system['ClientActivity'],
                'VideoRAM'                  => $system['VideoRAM'],
                'PatchUnknown'              => $system['PatchUnknown'],
                'ADLastLogon'               => $ad_last_logon,
                'NETFrameworkAvailable'     => $system['NETFrameworkAvailable'],
                'OSRoundup'                 => $system['OSRoundup'],
                'SCCMLastHealthResult'      => $system['SCCMLastHealthResult'],
            ];
        }
        Log::info('[+] ...SCCM systems normalization DONE');

        file_put_contents(storage_path('app/collections/sccm_systems_collection.json'), \Metaclassing\Utility::encodeJson($systems));

        Log::info('[+] sending SCCM systems data to Kafka...');
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($systems as $system) {
            $result = $producer->send([
                [
                    'topic' => 'sccm_systems',
                    'value' => \Metaclassing\Utility::encodeJson($system),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                //Log::info('[*] Data successfully sent to Kafka: '.$system['SystemName']);
            }
        }
        Log::info('[+] ...SCCM systems to Kafka DONE');

        Log::info('* Completed SCCM system processing! *'.PHP_EOL);
    }

    /**
     * Delete any SCCM system models that were not updated.
     *
     * @return \Illuminate\Http\Response
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subDays(1)->toDateString();

        $systems = SCCMSystem::all();

        foreach ($systems as $system) {
            $updated_at = substr($system->updated_at, 0, -9);

            if ($updated_at <= $delete_date) {
                Log::info('deleting SCCM system: '.$system->system_name.' (last updated: '.$system->updated_at.')');
                $system->delete();
            }
        }
    }

    /**
     * Handle date values from SCCM.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleDate($date)
    {
        if ($date) {
            $retval = Carbon::createFromFormat('n/j/Y G:i:s', $date);
        } else {
            $retval = null;
        }

        return $retval;
    }

    /**
     * Function to convert CSV into assoc array.
     *
     * @return array
     */
    public function csvToArray($file, $delimiter)
    {
        if (($handle = fopen($file, 'r')) !== false) {
            $i = 0;

            while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== false) {
                for ($j = 0; $j < count($lineArray); $j++) {
                    $arr[$i][$j] = $lineArray[$j];
                }
                $i++;
            }

            fclose($handle);
        }

        return $arr;
    }
}
