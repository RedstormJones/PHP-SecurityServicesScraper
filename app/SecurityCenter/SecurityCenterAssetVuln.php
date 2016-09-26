<?php

namespace App\SecurityCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityCenterAssetVuln extends Model
{
	use SoftDeletes;

	protected $dates = ['deleted_at'];

	protected $table = 'security_center_asset_vulns';

	/**
	* The attributes that are mass assignable.
	*
	* @var array
	*/
	protected $fillable = [
		'asset_name',
		'asset_id',
		'asset_score',
		'critical_vulns',
		'high_vulns',
		'medium_vulns',
		'total_vulns',
		'data',
	];

}
