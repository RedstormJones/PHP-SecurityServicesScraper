<?php

namespace App\ServiceNow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceNowIncident extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'service_now_incidents';

    /*
    * The attributes that are mass assignable
    *
    * @var array
    */
    protected $fillable = [
        'incident_id',
        'opened_at',
        'closed_at',
        'state',
        'duration',
        'initial_assignment_group',
        'sys_id',
        'time_worked',
        'reopen_count',
        'urgency',
        'impact',
        'severity',
        'priority',
        'email_contact',
        'description',
        'district',
        'updated_on',
        'active',
        'assignment_group',
        'caller_id',
        'department',
        'reassignment_count',
        'short_description',
        'resolved_by',
        'calendar_duration',
        'assigned_to',
        'resolved_at',
        'cmdb_ci',
        'opened_by',
        'escalation',
        'modified_count',
        'data'
    ];
}
