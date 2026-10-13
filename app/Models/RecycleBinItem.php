<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecycleBinItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'original_name',
        'original_path',
        'trash_name',
        'trash_path',
        'size_bytes',
        'is_directory',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'is_directory' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get human-readable size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
