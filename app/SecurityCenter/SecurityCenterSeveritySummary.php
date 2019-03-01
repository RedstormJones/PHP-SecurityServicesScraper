<?php

namespace App\SecurityCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityCenterSeveritySummary extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'security_center_severity_summaries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'severity_id',
        'severity_name',
        'severity_count',
        'severity_desc',
        'data',
    ];
}
