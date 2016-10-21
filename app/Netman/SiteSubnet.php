<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteSubnet extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'site_subnets';

    protected $fillable = [
        'ip_address',
        'ip_prefix',
        'netmask',
        'site',
        'data',
    ];
}
