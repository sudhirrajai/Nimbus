<?php

namespace App\Jobs;

use App\Models\GitDeployment;
use App\Services\GitDeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     * Deployments can take a while (cloning, npm install, etc.)
     */
    public int $timeout = 600; // 10 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GitDeployment $deployment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GitDeploymentService $deploymentService): void
    {
        Log::info("RunDeploymentJob started for {$this->deployment->domain} (ID: {$this->deployment->id})");

        try {
            $success = $deploymentService->deploy($this->deployment);

            if ($success) {
                Log::info("RunDeploymentJob completed successfully for {$this->deployment->domain}");
            } else {
                Log::warning("RunDeploymentJob finished with failure for {$this->deployment->domain}");
            }
        } catch (\Exception $e) {
            Log::error("RunDeploymentJob exception for {$this->deployment->domain}: " . $e->getMessage());

            $this->deployment->update([
                'status' => 'failed',
                'last_error' => 'Job exception: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("RunDeploymentJob FAILED for {$this->deployment->domain}: " . $exception?->getMessage());

        $this->deployment->update([
            'status' => 'failed',
            'last_error' => 'Deployment job failed: ' . ($exception?->getMessage() ?? 'Unknown error'),
        ]);
    }
}
