<?php

namespace App\SecurityCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityCenterHigh extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'security_center_highs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'dns_name',
        'severity_id',
        'severity_name',
        'risk_factor',
        'first_seen',
        'last_seen',
        'protocol',
        'ip_address',
        'port',
        'mac_address',
        'exploit_available',
        'exploit_ease',
        'exploit_frameworks',
        'vuln_public_date',
        'patch_public_date',
        'has_been_mitigated',
        'solution',
        'plugin_id',
        'plugin_name',
        'synopsis',
        'cpe',
        'data',
    ];
}
