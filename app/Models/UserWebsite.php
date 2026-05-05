<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWebsite extends Model
{
    protected $fillable = [
        'user_id',
        'domain',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this assignment has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $perms = $this->permissions ?? [];
        return in_array($permission, $perms);
    }
}
