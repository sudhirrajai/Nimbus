<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMetric extends Model
{
    public $timestamps = false;

    protected $table = 'project_metrics';

    protected $fillable = [
        'domain',
        'system_user',
        'cpu_percent',
        'memory_mb',
        'memory_percent',
        'disk_mb',
        'process_count',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cpu_percent' => 'float',
        'memory_mb' => 'float',
        'memory_percent' => 'float',
        'disk_mb' => 'float',
        'process_count' => 'integer',
    ];
}
