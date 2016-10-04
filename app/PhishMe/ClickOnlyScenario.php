<?php

namespace App\PhishMe;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClickOnlyScenario extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'scenario_id',
        'scenario_type',
        'email',
        'recipient_name',
        'recipient_group',
        'department',
        'location',
        'clicked_link',
        'clicked_link_timestamp',
        'reported_phish',
        'new_repeat_reporter',
        'reported_phish_timestamp',
        'time_to_report',
        'remote_ip',
        'geoip_country',
        'geoip_city',
        'geoip_organization',
        'last_dsn',
        'last_email_status',
        'last_email_status_timestamp',
        'language',
        'browser',
        'user_agent',
        'mobile',
        'seconds_spent_on_education',
        'data',
    ];


    /**
    * Get all of the click only reports
    *
    * @return mixed
    */
	public function reports()
    {
        return $this->morphMany('App\PhishMe\PhishMeScenario', 'reportable');
    }

}	// end of ClickOnlyScenario class
