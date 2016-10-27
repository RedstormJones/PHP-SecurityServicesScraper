<?php

namespace App\ServiceNow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class cmdbServer extends Model
{
    use softDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'cmdb_servers';

    /**
    * These are the fields that are mass assignable
    *
    * @var array
    */
    protected $fillable = [
        'cmdb_id',
        'name',
        'created_on',
        'updated_on',
        'created_by',
        'updated_by',
        'class_name',
        'modified_count',
        'serial_number',
        'managed_by',
        'owned_by',
        'supported_by',
        'support_group',
        'location',
        'department',
        'company',
        'data',
    ];
}
