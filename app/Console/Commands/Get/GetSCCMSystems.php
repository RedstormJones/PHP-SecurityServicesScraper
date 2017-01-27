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

        /***********************************
         * Process new SCCM systems upload *
         ***********************************/

        Log::info(PHP_EOL.PHP_EOL.'********************************************'.PHP_EOL.'* Starting SCCM systems upload processing! *'.PHP_EOL.'********************************************');

        $contents = file_get_contents(storage_path('app/collections/sccm_systems_collection.json'));
        $sccm_systems = \Metaclassing\Utility::decodeJson($contents);

        foreach ($sccm_systems as $system) {
            $exists = SCCMSystem::where('system_name', $system['system_name'])->value('id');

            if ($exists) {
                Log::info('updating SCCM system model: '.$system['system_name']);

                $image_date = $this->handleDate($system['image_date']);
                $ad_last_logon = $this->handleDate($system['ad_last_logon']);
                $ad_password_last_set = $this->handleDate($system['ad_password_last_set']);
                $ad_modified = $this->handleDate($system['ad_modified']);
                $sccm_last_heartbeat = $this->handleDate($system['sccm_last_heartbeat']);
                $sccm_last_health_eval = $this->handleDate($system['sccm_last_health_eval']);

                if ($system['days_since_last_logon'] != '') {
                    $days_since_last_logon = $system['days_since_last_logon'];
                } else {
                    $days_since_last_logon = 0;
                }

                // update model
                $system_model = SCCMSystem::findOrFail($exists);

                $system_model->update([
                    'district'                  => $system['district'],
                    'region'                    => $system['region'],
                    'group'                     => $system['group'],
                    'owner'                     => $system['owner'],
                    'days_since_last_logon'     => $days_since_last_logon,
                    'stale_45days'              => $system['stale_45days'],
                    'client_status'             => $system['client_status'],
                    'client_version'            => $system['client_version'],
                    'operating_system'          => $system['operating_system'],
                    'operating_system_version'  => $system['operating_system_version'],
                    'os_roundup'                => $system['os_roundup'],
                    'os_arch'                   => $system['os_arch'],
                    'system_role'               => $system['system_role'],
                    'serial_number'             => $system['serial_number'],
                    'chassis_type'              => $system['chassis_type'],
                    'manufacturer'              => $system['manufacturer'],
                    'model'                     => $system['model'],
                    'processor'                 => $system['processor'],
                    'image_source'              => $system['image_source'],
                    'image_date'                => $image_date,
                    'coe_compliant'             => $system['coe_compliant'],
                    'ps_version'                => $system['ps_version'],
                    'patch_total'               => $system['patch_total'],
                    'patch_installed'           => $system['patch_installed'],
                    'patch_missing'             => $system['patch_missing'],
                    'patch_unknown'             => $system['patch_unknown'],
                    'patch_percent'             => $system['patch_percent'],
                    'scep_installed'            => $system['scep_installed'],
                    'cylance_installed'         => $system['cylance_installed'],
                    'anyconnect_installed'      => $system['anyconnect_installed'],
                    'anyconnect_websecurity'    => $system['anyconnect_websecurity'],
                    'bitlocker_status'          => $system['bitlocker_status'],
                    'tpm_enabled'               => $system['tpm_enabled'],
                    'tpm_activated'             => $system['tpm_activated'],
                    'tpm_owned'                 => $system['tpm_owned'],
                    'ie_version'                => $system['ie_version'],
                    'ad_location'               => $system['ad_location'],
                    'primary_users'             => $system['primary_users'],
                    'last_logon_username'       => $system['last_logon_username'],
                    'ad_last_logon'             => $ad_last_logon,
                    'ad_password_last_set'      => $ad_password_last_set,
                    'ad_modified'               => $ad_modified,
                    'sccm_last_heartbeat'       => $sccm_last_heartbeat,
                    'sccm_management_point'     => $system['sccm_management_point'],
                    'sccm_last_health_eval'     => $sccm_last_health_eval,
                    'sccm_last_health_result'   => $system['sccm_last_health_result'],
                    'report_date'               => $system['report_date'],
                    'data'                      => \Metaclassing\Utility::encodeJson($system),
                ]);

                $system_model->save();

                // touch system model to update the 'updated_at' timestamp in case nothing was changed
                $system_model->touch();
            } else {
                // create model
                Log::info('creating new SCCM system model: '.$system['system_name']);

                $image_date = $this->handleDate($system['image_date']);
                $ad_last_logon = $this->handleDate($system['ad_last_logon']);
                $ad_password_last_set = $this->handleDate($system['ad_password_last_set']);
                $ad_modified = $this->handleDate($system['ad_modified']);
                $sccm_last_heartbeat = $this->handleDate($system['sccm_last_heartbeat']);
                $sccm_last_health_eval = $this->handleDate($system['sccm_last_health_eval']);

                if ($system['days_since_last_logon'] != '') {
                    $days_since_last_logon = $system['days_since_last_logon'];
                } else {
                    $days_since_last_logon = 0;
                }

                $system_model = new SCCMSystem();

                $system_model->system_name = $system['system_name'];
                $system_model->district = $system['district'];
                $system_model->region = $system['region'];
                $system_model->group = $system['group'];
                $system_model->owner = $system['owner'];
                $system_model->days_since_last_logon = $days_since_last_logon;
                $system_model->stale_45days = $system['stale_45days'];
                $system_model->client_status = $system['client_status'];
                $system_model->client_version = $system['client_version'];
                $system_model->operating_system = $system['operating_system'];
                $system_model->operating_system_version = $system['operating_system_version'];
                $system_model->os_roundup = $system['os_roundup'];
                $system_model->os_arch = $system['os_arch'];
                $system_model->system_role = $system['system_role'];
                $system_model->serial_number = $system['serial_number'];
                $system_model->chassis_type = $system['chassis_type'];
                $system_model->manufacturer = $system['manufacturer'];
                $system_model->model = $system['model'];
                $system_model->processor = $system['processor'];
                $system_model->image_source = $system['image_source'];
                $system_model->image_date = $image_date;
                $system_model->coe_compliant = $system['coe_compliant'];
                $system_model->ps_version = $system['ps_version'];
                $system_model->patch_total = $system['patch_total'];
                $system_model->patch_installed = $system['patch_installed'];
                $system_model->patch_missing = $system['patch_missing'];
                $system_model->patch_unknown = $system['patch_unknown'];
                $system_model->patch_percent = $system['patch_percent'];
                $system_model->scep_installed = $system['scep_installed'];
                $system_model->cylance_installed = $system['cylance_installed'];
                $system_model->anyconnect_installed = $system['anyconnect_installed'];
                $system_model->anyconnect_websecurity = $system['anyconnect_websecurity'];
                $system_model->bitlocker_status = $system['bitlocker_status'];
                $system_model->tpm_enabled = $system['tpm_enabled'];
                $system_model->tpm_activated = $system['tpm_activated'];
                $system_model->tpm_owned = $system['tpm_owned'];
                $system_model->ie_version = $system['ie_version'];
                $system_model->ad_location = $system['ad_location'];
                $system_model->primary_users = $system['primary_users'];
                $system_model->last_logon_username = $system['last_logon_username'];
                $system_model->ad_last_logon = $ad_last_logon;
                $system_model->ad_password_last_set = $ad_password_last_set;
                $system_model->ad_modified = $ad_modified;
                $system_model->sccm_last_heartbeat = $sccm_last_heartbeat;
                $system_model->sccm_management_point = $system['sccm_management_point'];
                $system_model->sccm_last_health_eval = $sccm_last_health_eval;
                $system_model->sccm_last_health_result = $system['sccm_last_health_result'];
                $system_model->report_date = $system['report_date'];
                $system_model->data = \Metaclassing\Utility::encodeJson($system);

                $system_model->save();
            }
        }

        Log::info('processing deletes...');
        $this->processDeletes();

        event(new SCCMSystemsCompleted());
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
}
