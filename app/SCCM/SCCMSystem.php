<?php

namespace App\SCCM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SCCMSystem extends Model
{
    use softDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'sccm_systems';

    /**
     * These are the fields that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'district',
        'region',
        'group',
        'owner',
        'days_since_last_logon',
        'stale_45days',
        'client_status',
        'client_version',
        'operating_system',
        'operating_system_version',
        'os_roundup',
        'os_arch',
        'system_role',
        'serial_number',
        'chassis_type',
        'manufacturer',
        'model',
        'processor',
        'physical_ram',
        'image_source',
        'image_date',
        'coe_compliant',
        'ps_version',
        'patch_total',
        'patch_installed',
        'patch_missing',
        'patch_unknown',
        'patch_percent',
        'scep_installed',
        'cylance_installed',
        'anyconnect_installed',
        'anyconnect_websecurity',
        'bitlocker_status',
        'tpm_enabled',
        'tpm_activated',
        'tpm_owned',
        'ie_version',
        'ad_location',
        'primary_users',
        'last_logon_username',
        'ad_last_logon',
        'ad_password_last_set',
        'ad_modified',
        'sccm_last_heartbeat',
        'sccm_management_point',
        'sccm_last_health_eval',
        'sccm_last_health_result',
        'report_date',
    ];
}
