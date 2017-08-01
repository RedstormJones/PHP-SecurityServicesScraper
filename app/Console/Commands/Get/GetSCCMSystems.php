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

        Log::info('building SCCM systems associative array...');

        // Bring it all together
        for ($j = 0; $j < $count; $j++) {
            $d = array_combine($keys, $sccm_data[$j]);
            $sccm_systems[$j] = $d;
        }

        $systems = [];

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

            $systems[] = [
                'ReportDate'                => $system['ReportDate'],
                'ADModified'                => $ad_modified,
                'Group'                     => $system['Group'],
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
                'ReportGroup'               => $system['ReportGroup'],
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
                'SiteID'                    => $system['SiteID'],
                'SCCMLastSWScan'            => $sccm_last_sw_scan,
                'PrimaryUsers'              => $system['PrimaryUsers'],
                'Region'                    => $system['Region'],
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

        file_put_contents(storage_path('app/collections/sccm_systems_collection.json'), \Metaclassing\Utility::encodeJson($systems));

        $cookiejar = storage_path('app/cookies/elasticsearch_cookie.txt');
        $crawler = new \Crawler\Crawler($cookiejar);

        $headers = [
            'Content-Type: application/json',
        ];

        // setup curl HTTP headers with $headers
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        foreach ($systems as $system) {
            $url = 'http://10.243.32.36:9200/sccm_systems/sccm_systems/'.$system['SystemName'];
            Log::info('HTTP Post to elasticsearch: '.$url);

            $post = [
                'doc'           => $system,
                'doc_as_upsert' => true,
            ];

            $json_response = $crawler->post($url, '', \Metaclassing\Utility::encodeJson($post));

            $response = \Metaclassing\Utility::decodeJson($json_response);
            Log::info($response);

            if (!array_key_exists('error', $response) && $response['_shards']['failed'] == 0) {
                Log::info('SCCM system was successfully inserted into ES: '.$system['SystemName']);
            } else {
                Log::error('Something went wrong inserting SCCM system: '.$system['SystemName']);
                die('Something went wrong inserting SCCM system: '.$system['SystemName'].PHP_EOL);
            }
        }

        /***********************************
         * Process new SCCM systems upload *
         ***********************************/

        /*
        Log::info(PHP_EOL.PHP_EOL.'********************************************'.PHP_EOL.'* Starting SCCM systems upload processing! *'.PHP_EOL.'********************************************');

        foreach ($systems as $system) {
            $exists = SCCMSystem::where('system_name', $system['SystemName'])->value('id');

            if ($exists) {
                Log::info('updating SCCM system model '.$system['SystemName'].' - client_activity: '.$system['ClientActivity']);

                // update model
                $system_model = SCCMSystem::findOrFail($exists);

                $system_model->update([
                    'district'                  => $system['District'],
                    'region'                    => $system['Region'],
                    'group'                     => $system['Group'],
                    'owner'                     => $system['Owner'],
                    'days_since_last_logon'     => $system['DaysLastLogon'],
                    'stale_45days'              => $system['Stale45Days'],
                    'client_activity'           => $system['ClientActivity'],
                    'client_status'             => $system['ClientStatus'],
                    'client_version'            => $system['ClientVersion'],
                    'operating_system'          => $system['OperatingSystem'],
                    'operating_system_version'  => $system['OperatingSystemVersion'],
                    'os_roundup'                => $system['OSRoundup'],
                    'os_arch'                   => $system['OSArch'],
                    'system_role'               => $system['SystemRole'],
                    'serial_number'             => $system['SerialNumber'],
                    'chassis_type'              => $system['ChassisType'],
                    'manufacturer'              => $system['Manufacturer'],
                    'model'                     => $system['Model'],
                    'processor'                 => $system['Processor'],
                    'image_source'              => $system['ImageSource'],
                    'image_date'                => $system['ImageDate'],
                    'coe_compliant'             => $system['COECompliant'],
                    'ps_version'                => $system['PowerShellVersion'],
                    'patch_total'               => $system['PatchTotal'],
                    'patch_installed'           => $system['PatchInstalled'],
                    'patch_missing'             => $system['PatchMissing'],
                    'patch_unknown'             => $system['PatchUnknown'],
                    'patch_percent'             => $system['PatchPercent'],
                    'scep_installed'            => $system['SCEPInstalled'],
                    'cylance_installed'         => $system['CylanceInstalled'],
                    'anyconnect_installed'      => $system['AnyConnectInstalled'],
                    'anyconnect_websecurity'    => $system['AnyConnectWebSecurity'],
                    'bitlocker_status'          => $system['BitLockerStatus'],
                    'tpm_enabled'               => $system['TPM_IsEnabled'],
                    'tpm_activated'             => $system['TPM_IsActivated'],
                    'tpm_owned'                 => $system['TPM_IsOwned'],
                    'ie_version'                => $system['IEVersion'],
                    'ad_location'               => $system['ADLocation'],
                    'primary_users'             => $system['PrimaryUsers'],
                    'last_logon_username'       => $system['LastLogonUserName'],
                    'ad_last_logon'             => $system['ADLastLogon'],
                    'ad_password_last_set'      => $system['ADPasswordLastSet'],
                    'ad_modified'               => $system['ADModified'],
                    'sccm_last_heartbeat'       => $system['SCCMLastHeartBeat'],
                    'sccm_management_point'     => $system['SCCMManagementPoint'],
                    'sccm_last_health_eval'     => $system['SCCMLastHealthEval'],
                    'sccm_last_health_result'   => $system['SCCMLastHealthResult'],
                    'report_date'               => $system['ReportDate'],
                    'data'                      => \Metaclassing\Utility::encodeJson($system),
                ]);

                $system_model->save();

                // touch system model to update the 'updated_at' timestamp in case nothing was changed
                $system_model->touch();
            } else {
                // create model
                Log::info('creating new SCCM system model: '.$system['SystemName']);

                $system_model = new SCCMSystem();

                $system_model->system_name = $system['SystemName'];
                $system_model->district = $system['District'];
                $system_model->region = $system['Region'];
                $system_model->group = $system['Group'];
                $system_model->owner = $system['Owner'];
                $system_model->days_since_last_logon = $system['DaysLastLogon'];
                $system_model->stale_45days = $system['Stale45Days'];
                $system_model->client_activity = $system['ClientActivity'];
                $system_model->client_status = $system['ClientStatus'];
                $system_model->client_version = $system['ClientVersion'];
                $system_model->operating_system = $system['OperatingSystem'];
                $system_model->operating_system_version = $system['OperatingSystemVersion'];
                $system_model->os_roundup = $system['OSRoundup'];
                $system_model->os_arch = $system['OSArch'];
                $system_model->system_role = $system['SystemRole'];
                $system_model->serial_number = $system['SerialNumber'];
                $system_model->chassis_type = $system['ChassisType'];
                $system_model->manufacturer = $system['Manufacturer'];
                $system_model->model = $system['Model'];
                $system_model->processor = $system['Processor'];
                $system_model->image_source = $system['ImageSource'];
                $system_model->image_date = $system['ImageDate'];
                $system_model->coe_compliant = $system['COECompliant'];
                $system_model->ps_version = $system['PowerShellVersion'];
                $system_model->patch_total = $system['PatchTotal'];
                $system_model->patch_installed = $system['PatchInstalled'];
                $system_model->patch_missing = $system['PatchMissing'];
                $system_model->patch_unknown = $system['PatchUnknown'];
                $system_model->patch_percent = $system['PatchPercent'];
                $system_model->scep_installed = $system['SCEPInstalled'];
                $system_model->cylance_installed = $system['CylanceInstalled'];
                $system_model->anyconnect_installed = $system['AnyConnectInstalled'];
                $system_model->anyconnect_websecurity = $system['AnyConnectWebSecurity'];
                $system_model->bitlocker_status = $system['BitLockerStatus'];
                $system_model->tpm_enabled = $system['TPM_IsEnabled'];
                $system_model->tpm_activated = $system['TPM_IsActivated'];
                $system_model->tpm_owned = $system['TPM_IsOwned'];
                $system_model->ie_version = $system['IEVersion'];
                $system_model->ad_location = $system['ADLocation'];
                $system_model->primary_users = $system['PrimaryUsers'];
                $system_model->last_logon_username = $system['LastLogonUserName'];
                $system_model->ad_last_logon = $system['ADLastLogon'];
                $system_model->ad_password_last_set = $system['ADPasswordLastSet'];
                $system_model->ad_modified = $system['ADModified'];
                $system_model->sccm_last_heartbeat = $system['SCCMLastHeartBeat'];
                $system_model->sccm_management_point = $system['SCCMManagementPoint'];
                $system_model->sccm_last_health_eval = $system['SCCMLastHealthEval'];
                $system_model->sccm_last_health_result = $system['SCCMLastHealthResult'];
                $system_model->report_date = $system['ReportDate'];
                $system_model->data = \Metaclassing\Utility::encodeJson($system);

                $system_model->save();
            }
        }

        Log::info('processing deletes...');
        $this->processDeletes();
        */

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
            $retval = Carbon::createFromFormat('n/j/Y g:i:s', substr($date, 0, -3));
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
