<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GitDeployment extends Model
{
    protected $fillable = [
        'domain',
        'repo_url',
        'repo_type',
        'url_type',
        'access_token',
        'branch',
        'yaml_path',
        'yaml_config',
        'status',
        'last_error',
        'commit_hash',
        'system_user',
        'last_deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'yaml_config' => 'array',
            'access_token' => 'encrypted',
            'last_deployed_at' => 'datetime',
        ];
    }

    /**
     * Get deployment logs for this deployment.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class);
    }

    /**
     * Get the latest logs grouped by step.
     */
    public function latestLogs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class)->latest();
    }

    /**
     * Check if deployment is currently in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['cloning', 'installing', 'building']);
    }

    /**
     * Get the domain path on the server.
     */
    public function getDomainPath(): string
    {
        return '/var/www/' . $this->domain;
    }

    /**
     * Get status badge color for frontend.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'secondary',
            'cloning' => 'info',
            'installing' => 'warning',
            'building' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'secondary',
        };
    }
}
