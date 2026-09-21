<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    public $timestamps = false;

    protected $table = 'server_metrics';

    protected $fillable = [
        'cpu_percent',
        'memory_used_mb',
        'memory_total_mb',
        'memory_percent',
        'swap_used_mb',
        'disk_percent',
        'load_1min',
        'load_5min',
        'load_15min',
        'top_processes',
        'is_alert_level',
        'created_at',
    ];

    protected $casts = [
        'top_processes' => 'array',
        'is_alert_level' => 'boolean',
        'created_at' => 'datetime',
        'cpu_percent' => 'float',
        'memory_used_mb' => 'float',
        'memory_total_mb' => 'float',
        'memory_percent' => 'float',
        'swap_used_mb' => 'float',
        'disk_percent' => 'float',
        'load_1min' => 'float',
        'load_5min' => 'float',
        'load_15min' => 'float',
    ];
}
