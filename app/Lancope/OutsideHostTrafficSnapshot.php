<?php

namespace App\Lancope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutsideHostTrafficSnapshot extends Model
{
    use SoftDeletes;

    protected $table = 'outside_host_traffic_snapshots';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'application_id',
        'application_name',
        'time_period',
        'traffic_inbound_Bps',
        'traffic_outbound_Bps',
        'traffic_within_Bps',
        'data',
    ];
}
