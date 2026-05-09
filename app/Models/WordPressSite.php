<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressSite extends Model
{
    protected $table = 'wordpress_sites';

    protected $fillable = [
        'domain',
        'path',
        'wp_version',
        'php_version',
        'db_name',
        'db_user',
        'db_password',
        'admin_user',
        'admin_email',
        'site_title',
        'status',
        'auto_update',
        'ssl_enabled',
        'last_checked_at',
        'notes',
    ];

    protected $casts = [
        'auto_update' => 'boolean',
        'ssl_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'db_password' => 'encrypted',
    ];

    /**
     * Hide sensitive fields from JSON serialization
     */
    protected $hidden = [
        'db_password',
    ];
}
