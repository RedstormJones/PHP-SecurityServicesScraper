<?php

namespace App\PhishMe;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhishMeScenario extends Model
{
	use SoftDeletes;

	protected $dates = ['deleted_at'];

	protected $table = 'phish_me_scenarios';

	protected $fillable = [
		'reportable_id',
		'reportable_type',
		'data',
	];

	/**
	* Get all of the owning reportable models.
	*
	* @return mixed
	*/
	public function reportable()
	{
		return $this->morphTo();
	}

}	// end of PhishMeScenario class
