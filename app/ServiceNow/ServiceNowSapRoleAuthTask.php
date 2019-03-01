<?php

namespace App\ServiceNow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceNowSapRoleAuthTask extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'service_now_sap_role_auth_tasks';

    /*
    * The attributes that are mass assignable
    *
    * @var array
    */
    protected $fillable = [
        'task_id',
        'created_on',
        'created_by',
        'sys_id',
        'class_name',
        'parent',
        'active',
        'updated_on',
        'updated_by',
        'opened_at',
        'opened_by',
        'closed_at',
        'closed_by',
        'close_notes',
        'initial_assignment_group',
        'assignment_group',
        'assigned_to',
        'state',
        'urgency',
        'impact',
        'priority',
        'time_worked',
        'short_description',
        'description',
        'work_notes',
        'comments',
        'reassignment_count',
        'district',
        'company',
        'department',
        'modified_count',
        'location',
        'cause_code',
        'data',
   ];
}
