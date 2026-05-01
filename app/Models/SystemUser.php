<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUser extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'home_directory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the panel user this system user belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sites directory for this user.
     */
    public function getSitesPath(): string
    {
        return $this->home_directory . '/sites';
    }
}
