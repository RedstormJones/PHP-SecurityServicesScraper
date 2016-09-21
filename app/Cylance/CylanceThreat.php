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
		'md5',
		'virustotal',
		'full_classification',
		'is_unique_to_cylance',
		'detected_by',
		'threat_priority',
		'current_model',
		'priority',
		'file_size',
		'global_quarantined',
		'data'
	];

}
