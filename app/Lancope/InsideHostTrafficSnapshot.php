<?php

namespace App\Lancope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsideHostTrafficSnapshot extends Model
{
    use SoftDeletes;

    protected $table = 'inside_host_traffic_snapshots';

    protected $dates = ['deleted_at'];

    /**
    * The attributes that are mass assignable
    *
    * @var array
    */
    protected $fillable = [
        'application_id',
        'application_name',
        'traffic_outbound_Bps',
        'traffic_inbound_Bps',
        'traffic_within_Bps',
        'time_period',
        'data',
    ];
}
