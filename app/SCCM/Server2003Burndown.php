<?php

namespace App\SCCM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server2003Burndown extends Model
{
    use softDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'server2003_burndowns';

    /**
     * These are the fields that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'server_count',
        'trend_value',
        'data',
    ];
}
