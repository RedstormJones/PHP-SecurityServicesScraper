<?php

namespace App\Console\Commands\Get;

use App\Events\SCCMSystemsCompleted;
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

        file_put_contents(storage_path('app/collections/sccm_systems_collection.json'), \Metaclassing\Utility::encodeJson($sccm_systems));

        /***********************************
         * Process new SCCM systems upload *
         ***********************************/

        Log::info(PHP_EOL.PHP_EOL.'********************************************'.PHP_EOL.'* Starting SCCM systems upload processing! *'.PHP_EOL.'********************************************');

        foreach ($sccm_systems as $system) {
            $exists = SCCMSystem::where('system_name', $system['SystemName'])->value('id');

            if ($exists) {
                Log::info('updating SCCM system model '.$system['SystemName'].' - client_activity: '.$system['ClientActivity']);

                $image_date = $this->handleDate($system['ImageDate']);
                $ad_last_logon = $this->handleDate($system['ADLastLogon']);
                $ad_password_last_set = $this->handleDate($system['ADPasswordLastSet']);
                $ad_modified = $this->handleDate($system['ADModified']);
                $sccm_last_heartbeat = $this->handleDate($system['SCCMLastHeartBeat']);
                $sccm_last_health_eval = $this->handleDate($system['SCCMLastHealthEval']);

                if ($system['DaysLastLogon'] != '') {
                    $days_since_last_logon = $system['DaysLastLogon'];
                } else {
                    $days_since_last_logon = 0;
                }

                // update model
                $system_model = SCCMSystem::findOrFail($exists);

                $system_model->update([
                    'district'                  => $system['District'],
                    'region'                    => $system['Region'],
                    'group'                     => $system['Group'],
                    'owner'                     => $system['Owner'],
                    'days_since_last_logon'     => $days_since_last_logon,
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
                    'image_date'                => $image_date,
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
                    'ad_last_logon'             => $ad_last_logon,
                    'ad_password_last_set'      => $ad_password_last_set,
                    'ad_modified'               => $ad_modified,
                    'sccm_last_heartbeat'       => $sccm_last_heartbeat,
                    'sccm_management_point'     => $system['SCCMManagementPoint'],
                    'sccm_last_health_eval'     => $sccm_last_health_eval,
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

                $image_date = $this->handleDate($system['ImageDate']);
                $ad_last_logon = $this->handleDate($system['ADLastLogon']);
                $ad_password_last_set = $this->handleDate($system['ADPasswordLastSet']);
                $ad_modified = $this->handleDate($system['ADModified']);
                $sccm_last_heartbeat = $this->handleDate($system['SCCMLastHeartBeat']);
                $sccm_last_health_eval = $this->handleDate($system['SCCMLastHealthEval']);

                if ($system['DaysLastLogon'] != '') {
                    $days_since_last_logon = $system['DaysLastLogon'];
                } else {
                    $days_since_last_logon = 0;
                }

                $system_model = new SCCMSystem();

                $system_model->system_name = $system['SystemName'];
                $system_model->district = $system['District'];
                $system_model->region = $system['Region'];
                $system_model->group = $system['Group'];
                $system_model->owner = $system['Owner'];
                $system_model->days_since_last_logon = $days_since_last_logon;
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
                $system_model->image_date = $image_date;
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
                $system_model->ad_last_logon = $ad_last_logon;
                $system_model->ad_password_last_set = $ad_password_last_set;
                $system_model->ad_modified = $ad_modified;
                $system_model->sccm_last_heartbeat = $sccm_last_heartbeat;
                $system_model->sccm_management_point = $system['SCCMManagementPoint'];
                $system_model->sccm_last_health_eval = $sccm_last_health_eval;
                $system_model->sccm_last_health_result = $system['SCCMLastHealthResult'];
                $system_model->report_date = $system['ReportDate'];
                $system_model->data = \Metaclassing\Utility::encodeJson($system);

                $system_model->save();
            }
        }

        Log::info('processing deletes...');
        $this->processDeletes();

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
