<?php

namespace App\Cylance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CylanceDevice extends Model
{
	use SoftDeletes;

	protected $dates = ['deleted_at'];

	protected $table = 'cylance_devices';

    /*
	* The attributes that are mass assignable.
	*
	* @var array
	*/
	protected $fillable = [
		'device_id',
		'device_name',
		'zones_text',
		'files_unsafe',
		'files_quarantined',
		'files_abnormal',
		'files_waived',
		'files_analyzed',
		'agent_version_text',
		'last_users_text',
		'os_versions_text',
		'ip_addresses_text',
		'mac_addresses_text',
		'policy_name',
		'data'
	];


}
