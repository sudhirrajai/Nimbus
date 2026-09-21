<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupDestination extends Model
{
    protected $table = 'backup_destinations';

    protected $fillable = [
        'name',
        'driver',
        'is_default',
        'is_active',
        'credentials',
        'last_tested_at',
        'last_test_status',
        'last_test_error',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'credentials' => 'array',
        'last_tested_at' => 'datetime',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(BackupRecord::class, 'destination_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BackupSchedule::class, 'destination_id');
    }

    /**
     * Return credentials with sensitive values masked for frontend display
     */
    public function getSafeCredentialsAttribute(): array
    {
        $creds = $this->credentials ?: [];
        $masked = [];

        foreach ($creds as $key => $value) {
            if (empty($value)) {
                $masked[$key] = '';
                continue;
            }

            // Mask sensitive fields
            $sensitiveKeys = [
                'secret_key', 'secret_access_key', 'application_key', 'api_key',
                'client_secret', 'refresh_token', 'private_key', 'password'
            ];

            if (in_array(strtolower($key), $sensitiveKeys) || str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'key')) {
                if (is_string($value) && strlen($value) > 8) {
                    $masked[$key] = substr($value, 0, 4) . '••••••••' . substr($value, -4);
                } else {
                    $masked[$key] = '••••••••';
                }
            } elseif ($key === 'service_account_json') {
                $masked[$key] = !empty($value) ? '[Configured JSON Key]' : '';
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
