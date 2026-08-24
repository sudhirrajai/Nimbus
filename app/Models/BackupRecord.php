<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRecord extends Model
{
    protected $table = 'backup_records';

    protected $fillable = [
        'schedule_id',
        'domain',
        'database_name',
        'type',
        'file_name',
        'file_path',
        'size_bytes',
        'storage_driver',
        'status',
        'error_message',
        'checksum',
        'metadata',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BackupSchedule::class, 'schedule_id');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes ?: 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
