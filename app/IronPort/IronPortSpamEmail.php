<?php

namespace App\IronPort;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IronPortSpamEmail extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'iron_port_spam_emails';

    /*
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'mid',
        'subject',
        'size',
        'quarantine_names',
        'time_added',
        'reason',
        'recipients',
        'sender',
        'esa_id',
        'data',
    ];
}
