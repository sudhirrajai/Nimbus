<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtpAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'username',
        'homedir',
        'quota_mb',
        'is_active',
        'notes',
        'last_login_at',
    ];

    protected $casts = [
        'quota_mb' => 'integer',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
