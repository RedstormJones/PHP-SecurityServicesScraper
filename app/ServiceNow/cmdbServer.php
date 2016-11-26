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
     * These are the fields that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sys_id',
        'name',
        'created_on',
        'updated_on',
        'created_by',
        'updated_by',
        'classification',
        'modified_count',
        'short_description',
        'os_domain',
        'ip_address',
        'remote_mgmt_ip',
        'application',
        'environment',
        'data_center',
        'site_id',
        'business_process',
        'business_function',
        'notes',
        'product',
        'product_group',
        'antivirus_exclusions',
        'ktg_contact',
        'virtual',
        'used_for',
        'firewall_status',
        'os',
        'os_service_pack',
        'os_version',
        'disk_space',
        'operational_status',
        'model_number',
        'serial_number',
        'managed_by',
        'owned_by',
        'supported_by',
        'support_group',
        'location',
        'bpo',
        'assigned_to',
        'district',
        'data',
    ];
}
