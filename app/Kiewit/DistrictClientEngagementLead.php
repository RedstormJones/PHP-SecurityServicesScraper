<?php

namespace App\Kiewit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistrictClientEngagementLead extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'district_client_engagement_leads';

    protected $fillable = [
    	'district',
    	'email',
    	'data',
    ];

}
