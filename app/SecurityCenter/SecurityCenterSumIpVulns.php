<?php

namespace App\SecurityCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityCenterSumIpVulns extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'security_center_sum_ip_vulns';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    	'ip_address',
    	'dns_name',
    	'score',
    	'total',
    	'severity_info',
    	'severity_low',
    	'severity_medium',
    	'severity_high',
    	'severity_critical',
    	'mac_address',
    	'policy_name',
    	'plugin_set',
    	'netbios_name',
    	'os_cpe',
    	'bios_guid',
    	'repository_id',
    	'repository_name',
    	'repository_desc',
    	'data',
    ];
}
