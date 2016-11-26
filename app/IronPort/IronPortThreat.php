<?php

namespace App\IronPort;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IronPortThreat extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'iron_port_threats';

    /*
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'begin_date',
        'end_date',
        'category',
        'threat_type',
        'total_messages',
        'data',
    ];
}
