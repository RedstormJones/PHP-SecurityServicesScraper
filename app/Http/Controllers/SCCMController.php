<?php

namespace App\Http\Controllers;

use App\SCCM\SCCMSystem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class SCCMController extends Controller
{
    /**
     * Create a new SCCM Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function uploadAllSystems(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $input = $request->all();

            Log::info($input);

            foreach ($input as $attribute) {
                foreach ($attribute as $key => $value) {
                    $data[$key] = $value;
                }
            }

            $this->processSCCMSystem($data);

            $response = [
                'success'      => true,
                'input'        => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'      => false,
                'message'      => 'Failed to upload systems from SCCM dump.',
                'exception'    => $e,
            ];
        }

        return response()->json($response);
    }

    public function processSCCMSystem($system)
    {
        $exists = SCCMSystem::where('system_name', $system['system_name'])->value('id');

        if ($exists) {
            // update model
            $system_model = SCCMSystem::findOrFail($exists);

            $system_model->update([
                'district'                  => $system['district'],
                'region'                    => $system['region'],
                'group'                     => $system['group'],
                'owner'                     => $system['owner'],
                'days_since_last_logon'     => $system['days_since_last_logon'],
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
                'image_date'                => $system['image_date'],
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
                'ad_last_logon'             => $system['ad_last_logon'],
                'ad_password_last_set'      => $system['ad_password_last_set'],
                'ad_modified'               => $system['ad_modified'],
                'sccm_last_heartbeat'       => $system['sccm_last_heartbeat'],
                'sccm_management_point'     => $system['sccm_management_point'],
                'sccm_last_health_eval'     => $system['sccm_last_health_eval'],
                'sccm_last_health_result'   => $system['sccm_last_health_result'],
                'report_date'               => $system['report_date'],
                'data'                      => \Metaclassing\Utility::encodeJson($system),
            ]);

            $system_model->save();

            // touch system model to update the 'updated_at' timestamp in case nothing was changed
            $system_model->touch();

            Log::info('updated SCCM system: '.$system['system_name']);
        } else {
            // create model
            Log::info('creating new SCCM system model for: '.$system['system_name']);

            $system_model = new SCCMSystem();

            $system_model->system_name = $system['system_name'];
            $system_model->district = $system['district'];
            $system_model->region = $system['region'];
            $system_model->group = $system['group'];
            $system_model->owner = $system['owner'];
            $system_model->days_since_last_logon = $system['days_since_last_logon'];
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
            $system_model->image_date = $system['image_date'];
            $system_model->coe_compliant = $system['coe_compliant'];
            $system_model->ps_version = $system['ps_version'];
            $system_model->patch_total = $system['patch_total'];
            $system_model->patch_intalled = $system['patch_intalled'];
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
            $system_model->ad_last_logon = $system['ad_last_logon'];
            $system_model->ad_password_last_set = $system['ad_password_last_set'];
            $system_model->ad_modified = $system['ad_modified'];
            $system_model->sccm_last_heartbeat = $system['sccm_last_heartbeat'];
            $system_model->sccm_last_health_eval = $system['sccm_last_health_eval'];
            $system_model->sccm_last_health_result = $system['sccm_last_health_result'];
            $system_model->report_date = $system['report_date'];
            $system_model->data = \Metaclassing\Utility::encodeJson($system);

            $system_model->save();
        }

        $this->processDeletes();
    }

    public function processDeletes()
    {
        $delete_date = Carbon::now()->subDays(1)->toDateString();

        $systems = SCCMSystem::all();

        foreach ($systems as $system) {
            $updated_at = substr($system->updated_at, 0, -9);
            if ($updated_at <= $delete_date) {
                Log::info('deleting SCCM system: '.$system->system_name);
                $system->delete();
            }
        }
    }
}