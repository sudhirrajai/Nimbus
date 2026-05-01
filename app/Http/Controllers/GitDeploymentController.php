<?php

namespace App\Http\Controllers;

use App\Models\GitDeployment;
use App\Models\DeploymentLog;
use App\Models\CommandBlacklist;
use App\Jobs\RunDeploymentJob;
use App\Services\GitDeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GitDeploymentController extends Controller
{
    private GitDeploymentService $deploymentService;
    private string $basePath = '/var/www/';

    public function __construct(GitDeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }

    /**
     * Show the deployments dashboard.
     */
    public function index()
    {
        return Inertia::render('Deployments/Index');
    }

    /**
     * Show the create deployment wizard.
     */
    public function create()
    {
        return Inertia::render('Deployments/Create');
    }

    /**
     * Show deployment logs page.
     */
    public function showLogs($id)
    {
        return Inertia::render('Deployments/Logs', [
            'deploymentId' => (int) $id,
        ]);
    }

    /**
     * Get all deployments as JSON.
     */
    public function list()
    {
        try {
            $deployments = GitDeployment::orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($dep) {
                    return [
                        'id' => $dep->id,
                        'domain' => $dep->domain,
                        'repo_url' => $dep->repo_url,
                        'repo_type' => $dep->repo_type,
                        'url_type' => $dep->url_type,
                        'branch' => $dep->branch,
                        'status' => $dep->status,
                        'status_color' => $dep->getStatusColor(),
                        'commit_hash' => $dep->commit_hash ? substr($dep->commit_hash, 0, 7) : null,
                        'last_deployed_at' => $dep->last_deployed_at?->diffForHumans(),
                        'last_error' => $dep->last_error,
                        'has_yaml' => $dep->yaml_config !== null,
                        'is_in_progress' => $dep->isInProgress(),
                        'created_at' => $dep->created_at->format('Y-m-d H:i'),
                    ];
                });

            return response()->json($deployments);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load deployments: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get available domains for deployment.
     */
    public function getDomains()
    {
        try {
            if (!File::exists($this->basePath)) {
                return response()->json([]);
            }

            // Get all domain directories
            $allDomains = collect(File::directories($this->basePath))
                ->map(fn($path) => basename($path))
                ->filter(fn($name) => !in_array(strtolower($name), [
                    'html', 'default', 'public', 'cgi-bin', 'nimbus'
                ]))
                ->values();

            // Get domains that already have deployments
            $deployedDomains = GitDeployment::pluck('domain')->toArray();

            // Indicate which domains already have deployments
            $domains = $allDomains->map(function ($domain) use ($deployedDomains) {
                return [
                    'name' => $domain,
                    'has_deployment' => in_array($domain, $deployedDomains),
                ];
            });

            return response()->json($domains);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load domains: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create a new deployment configuration.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253',
                'repo_url' => ['required', 'string', 'max:500', 'regex:/^(https?:\/\/.+|git@.+:.+)$/'],
                'repo_type' => 'required|in:public,private',
                'url_type' => 'required|in:https,ssh',
                'access_token' => 'nullable|string|max:500',
                'branch' => 'required|string|max:255',
            ]);

            // Validate domain exists
            $domainPath = $this->basePath . $request->domain;
            if (!File::exists($domainPath)) {
                return response()->json([
                    'error' => "Domain directory does not exist. Please add the domain first."
                ], 422);
            }

            // Check for existing deployment on this domain
            if (GitDeployment::where('domain', $request->domain)->exists()) {
                return response()->json([
                    'error' => 'A deployment already exists for this domain. Delete it first to create a new one.'
                ], 409);
            }

            // Private repos need a token (for HTTPS)
            if ($request->repo_type === 'private' && $request->url_type === 'https' && empty($request->access_token)) {
                return response()->json([
                    'error' => 'Access token is required for private HTTPS repositories.'
                ], 422);
            }

            $deployment = GitDeployment::create([
                'domain' => $request->domain,
                'repo_url' => $request->repo_url,
                'repo_type' => $request->repo_type,
                'url_type' => $request->url_type,
                'access_token' => $request->access_token,
                'branch' => $request->branch,
                'status' => 'pending',
            ]);

            \Log::info("Deployment created for {$request->domain} by user " . auth()->id());

            return response()->json([
                'message' => 'Deployment configuration created successfully',
                'deployment' => [
                    'id' => $deployment->id,
                    'domain' => $deployment->domain,
                    'status' => $deployment->status,
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create deployment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Trigger a deployment.
     */
    public function deploy($id)
    {
        try {
            $deployment = GitDeployment::findOrFail($id);

            if ($deployment->isInProgress()) {
                return response()->json([
                    'error' => 'A deployment is already in progress for this domain.'
                ], 409);
            }

            // Reset status
            $deployment->update(['status' => 'pending', 'last_error' => null]);

            // Dispatch deployment as a background job
            RunDeploymentJob::dispatch($deployment);

            \Log::info("Deployment job dispatched for {$deployment->domain} by user " . auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Deployment started! It will run in the background. Check the logs for progress.',
                'status' => 'pending',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Deployment error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Redeploy an existing deployment (pull latest and rebuild).
     */
    public function redeploy($id)
    {
        return $this->deploy($id);
    }

    /**
     * Get deployment status and recent logs.
     */
    public function status($id)
    {
        try {
            $deployment = GitDeployment::with(['logs' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])->findOrFail($id);

            return response()->json([
                'id' => $deployment->id,
                'domain' => $deployment->domain,
                'repo_url' => $deployment->repo_url,
                'repo_type' => $deployment->repo_type,
                'url_type' => $deployment->url_type,
                'branch' => $deployment->branch,
                'status' => $deployment->status,
                'status_color' => $deployment->getStatusColor(),
                'commit_hash' => $deployment->commit_hash,
                'last_deployed_at' => $deployment->last_deployed_at?->format('Y-m-d H:i:s'),
                'last_error' => $deployment->last_error,
                'has_yaml' => $deployment->yaml_config !== null,
                'yaml_config' => $deployment->yaml_config,
                'is_in_progress' => $deployment->isInProgress(),
                'logs' => $deployment->logs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'step' => $log->step,
                        'step_label' => $log->getStepLabel(),
                        'status' => $log->status,
                        'status_icon' => $log->getStatusIcon(),
                        'output' => $log->output,
                        'command' => $log->command,
                        'duration_seconds' => $log->duration_seconds,
                        'created_at' => $log->created_at->format('H:i:s'),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get deployment logs.
     */
    public function logs($id)
    {
        try {
            $deployment = GitDeployment::findOrFail($id);
            $logs = DeploymentLog::where('git_deployment_id', $id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'step' => $log->step,
                        'step_label' => $log->getStepLabel(),
                        'status' => $log->status,
                        'status_icon' => $log->getStatusIcon(),
                        'output' => $log->output,
                        'command' => $log->command,
                        'duration_seconds' => $log->duration_seconds,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'deployment' => [
                    'id' => $deployment->id,
                    'domain' => $deployment->domain,
                    'status' => $deployment->status,
                    'status_color' => $deployment->getStatusColor(),
                    'branch' => $deployment->branch,
                    'commit_hash' => $deployment->commit_hash,
                ],
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load logs: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a deployment configuration.
     */
    public function destroy($id)
    {
        try {
            $deployment = GitDeployment::findOrFail($id);

            if ($deployment->isInProgress()) {
                return response()->json([
                    'error' => 'Cannot delete a deployment in progress.'
                ], 409);
            }

            $domain = $deployment->domain;
            $deployment->delete();

            \Log::info("Deployment deleted for {$domain} by user " . auth()->id());

            return response()->json([
                'message' => 'Deployment configuration deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete deployment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Validate repository connectivity.
     */
    public function validateRepo(Request $request)
    {
        try {
            $request->validate([
                'repo_url' => 'required|string|max:500',
                'repo_type' => 'required|in:public,private',
                'url_type' => 'required|in:https,ssh',
                'access_token' => 'nullable|string|max:500',
            ]);

            $result = $this->deploymentService->validateRepository(
                $request->repo_url,
                $request->repo_type,
                $request->access_token,
                $request->url_type
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['valid' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch branches from repository.
     */
    public function getBranches(Request $request)
    {
        try {
            $request->validate([
                'repo_url' => 'required|string|max:500',
                'repo_type' => 'required|in:public,private',
                'url_type' => 'required|in:https,ssh',
                'access_token' => 'nullable|string|max:500',
            ]);

            $result = $this->deploymentService->fetchBranches(
                $request->repo_url,
                $request->repo_type,
                $request->access_token,
                $request->url_type
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'branches' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get command blacklist entries.
     */
    public function getBlacklist()
    {
        try {
            $entries = CommandBlacklist::orderBy('type')->orderBy('pattern')->get();
            return response()->json($entries);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load blacklist: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add or update a blacklist entry.
     */
    public function updateBlacklist(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|in:add,update,delete,toggle',
                'id' => 'nullable|integer|exists:command_blacklist,id',
                'pattern' => 'nullable|string|max:500',
                'type' => 'nullable|in:exact,contains,regex',
                'description' => 'nullable|string|max:500',
            ]);

            switch ($request->action) {
                case 'add':
                    $entry = CommandBlacklist::create([
                        'pattern' => $request->pattern,
                        'type' => $request->type ?? 'contains',
                        'description' => $request->description,
                        'is_active' => true,
                    ]);
                    return response()->json(['message' => 'Blacklist entry added', 'entry' => $entry], 201);

                case 'update':
                    $entry = CommandBlacklist::findOrFail($request->id);
                    $entry->update($request->only(['pattern', 'type', 'description']));
                    return response()->json(['message' => 'Blacklist entry updated', 'entry' => $entry]);

                case 'delete':
                    CommandBlacklist::findOrFail($request->id)->delete();
                    return response()->json(['message' => 'Blacklist entry deleted']);

                case 'toggle':
                    $entry = CommandBlacklist::findOrFail($request->id);
                    $entry->update(['is_active' => !$entry->is_active]);
                    return response()->json(['message' => 'Blacklist entry toggled', 'entry' => $entry]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update blacklist: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get or generate the server's public SSH key for deployments.
     */
    public function getServerSshKey()
    {
        try {
            $sshDir = '/var/www/.ssh';
            $keyPath = "{$sshDir}/id_ed25519";
            $pubKeyPath = "{$keyPath}.pub";

            if (!File::exists($pubKeyPath)) {
                if (!File::exists($sshDir)) {
                    exec("sudo mkdir -p {$sshDir} 2>&1");
                    exec("sudo chown www-data:www-data {$sshDir} 2>&1");
                    exec("sudo chmod 700 {$sshDir} 2>&1");
                }

                // Generate ED25519 key for www-data
                exec("sudo -u www-data ssh-keygen -t ed25519 -f {$keyPath} -N '' -C 'nimbus-deploy@server' 2>&1", $output, $returnCode);
                
                if ($returnCode !== 0) {
                    throw new \Exception("Failed to generate SSH key: " . implode("\n", $output));
                }

                // Add github.com and others to known_hosts to prevent interactive prompts
                exec("sudo -u www-data ssh-keyscan -H github.com >> {$sshDir}/known_hosts 2>&1");
                exec("sudo -u www-data ssh-keyscan -H gitlab.com >> {$sshDir}/known_hosts 2>&1");
                exec("sudo -u www-data ssh-keyscan -H bitbucket.org >> {$sshDir}/known_hosts 2>&1");
            }

            $pubKey = File::get($pubKeyPath);

            return response()->json([
                'success' => true,
                'public_key' => trim($pubKey)
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get server SSH key: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve SSH key: ' . $e->getMessage()
            ], 500);
        }
    }
}
