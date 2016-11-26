<?php

namespace App\Cylance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CylanceThreat extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'cylance_threats';

    /*
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'threat_id',
        'common_name',
        'cylance_score',
        'active_in_devices',
        'allowed_in_devices',
        'blocked_in_devices',
        'suspicious_in_devices',
        'first_found',
        'last_found',
        'last_found_active',
        'last_found_allowed',
        'last_found_blocked',
        'md5',
        'virustotal',
        'is_virustotal_threat',
        'full_classification',
        'is_unique_to_cylance',
        'is_safelisted',
        'detected_by',
        'threat_priority',
        'current_model',
        'priority',
        'file_size',
        'global_quarantined',
        'signed',
        'cert_issuer',
        'cert_publisher',
        'cert_timestamp',
        'data',
    ];
}
