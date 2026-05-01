<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentLog extends Model
{
    protected $fillable = [
        'git_deployment_id',
        'step',
        'status',
        'output',
        'command',
        'duration_seconds',
    ];

    /**
     * Get the deployment this log belongs to.
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(GitDeployment::class, 'git_deployment_id');
    }

    /**
     * Get a human-readable step label.
     */
    public function getStepLabel(): string
    {
        return match ($this->step) {
            'clone' => 'Repository Clone',
            'yaml_parse' => 'YAML Configuration',
            'runtime_check' => 'Runtime Check',
            'install' => 'Install Dependencies',
            'build' => 'Build Project',
            'env_setup' => 'Environment Setup',
            'permissions' => 'Set Permissions',
            'nginx_update' => 'Nginx Configuration',
            default => ucfirst($this->step),
        };
    }

    /**
     * Get status icon for frontend.
     */
    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'running' => 'sync',
            'success' => 'check_circle',
            'failed' => 'error',
            'skipped' => 'skip_next',
            default => 'help',
        };
    }
}
