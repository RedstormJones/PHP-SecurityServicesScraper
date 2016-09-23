<?php

namespace App\IronPort;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomingEmail extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'incoming_emails';
    /*
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'begin_date',
        'end_date',
        'sender_domain',
        'connections_rejected',
        'connections_accepted',
        'total_attempted',
        'stopped_by_recipient_throttling',
        'stopped_by_reputation_filtering',
        'stopped_by_content_filter',
        'stopped_as_invalid_recipients',
        'spam_detected',
        'virus_detected',
        'amp_detected',
        'total_threats',
        'marketing',
        'social',
        'bulk',
        'total_graymails',
        'clean',
        'data',
    ];
}
