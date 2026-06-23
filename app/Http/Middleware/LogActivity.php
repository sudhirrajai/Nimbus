<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ActivityLog;

class LogActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful actions (status code 2xx or 3xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 400) {
            $this->logRequest($request);
        }

        return $response;
    }

    /**
     * Log request activity
     */
    private function logRequest(Request $request)
    {
        $method = $request->method();
        $routeName = $request->route()?->getName();
        $path = $request->path();

        // 1. Determine if this request should be logged
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
        
        // We also want to log page views (Inertia GET requests that are page loads)
        // Check if this is a standard GET request loading a page, not an AJAX API endpoint returning JSON
        $isPageView = ($method === 'GET' && !$request->expectsJson() && !str_starts_with($path, 'api/'));

        if (!$isMutation && !$isPageView) {
            return;
        }

        // Exclude specific paths like activity index, stats, updates checks, search, bug-reports to avoid spam
        if ($request->is('dashboard/stats') || 
            $request->is('api/search') || 
            $request->is('activities*') || 
            $request->is('logs/read*') || 
            $request->is('logs/download*') ||
            $request->is('shield/status') ||
            $request->is('database/install-status') ||
            $request->is('database/status') ||
            $request->is('email/status') ||
            $request->is('email/install-log') ||
            $request->is('supervisor/status') ||
            $request->is('supervisor/install-log') ||
            $request->is('supervisor/processes') ||
            $request->is('updates/status') ||
            $request->is('updates/check') ||
            $request->is('resources/usage') ||
            $request->is('setup*') ||
            $request->is('login*') ||
            $request->is('logout*')
        ) {
            return;
        }

        $action = 'view';
        $service = 'general';
        $description = '';

        if ($isPageView) {
            $action = 'view';
            // Determine service from path
            $segments = explode('/', $path);
            $service = $segments[0] ?? 'dashboard';
            if (empty($service)) {
                $service = 'dashboard';
            }
            $description = "Viewed page: " . ucfirst($service);
        } else {
            // Mutation log
            $action = match ($method) {
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => 'update',
            };

            // Override action for specific POST updates
            if ($method === 'POST') {
                if (str_contains($path, '/update') || 
                    str_contains($path, '/password') || 
                    str_contains($path, '/save') || 
                    str_contains($path, '/toggle') || 
                    str_contains($path, '/renew') || 
                    str_contains($path, '/assign') || 
                    str_contains($path, '/permissions') || 
                    str_contains($path, '/quota') || 
                    str_contains($path, '/sync') || 
                    str_contains($path, '/redeploy') || 
                    str_contains($path, '/blacklist')
                ) {
                    $action = 'update';
                }
            }

            // Analyze route or path
            $segments = explode('/', $path);
            $service = $segments[0] ?? 'general';

            // Custom descriptions based on route and inputs
            $description = $this->buildDescription($path, $request, $action);
        }

        if (!empty($description)) {
            ActivityLog::log($action, $service, $description);
        }
    }

    /**
     * Build friendly description for mutation events
     */
    private function buildDescription(string $path, Request $request, string $action): string
    {
        $fallback = ucfirst($action) . "d " . str_replace('-', ' ', explode('/', $path)[0] ?? 'resource');

        if (str_starts_with($path, 'database')) {
            if ($path === 'database/create') return "Created database: " . $request->input('name');
            if ($path === 'database/delete') return "Deleted database: " . $request->input('name');
            if ($path === 'database/user/create') return "Created database user: " . $request->input('username');
            if ($path === 'database/user/delete') return "Deleted database user: " . $request->input('username');
            if ($path === 'database/user/assign') return "Assigned user '" . $request->input('username') . "' to database '" . $request->input('database') . "'";
            if ($path === 'database/user/permissions') return "Updated permissions for database user '" . $request->input('username') . "' on '" . $request->input('database') . "'";
            if ($path === 'database/user/password') return "Updated password for database user: " . $request->input('username');
        }

        if (str_starts_with($path, 'domains')) {
            if ($path === 'domains' && $action === 'create') return "Created domain: " . $request->input('domain');
            if ($action === 'delete') return "Deleted domain: " . $request->route('domain');
            if ($action === 'update') return "Updated domain settings for: " . $request->route('domain');
        }

        if (str_starts_with($path, 'nginx')) {
            if ($path === 'nginx/config/save') return "Saved Nginx config for: " . $request->input('domain');
            if ($path === 'nginx/toggle') return ($request->input('enabled') ? "Enabled" : "Disabled") . " Nginx config for: " . $request->input('domain');
            if ($path === 'nginx/reload') return "Reloaded Nginx service";
            if ($path === 'nginx/test') return "Tested Nginx configuration";
        }

        if (str_starts_with($path, 'cron')) {
            if ($path === 'cron/create') return "Created cron job: " . $request->input('command');
            if ($path === 'cron/update') return "Updated cron job: " . $request->input('command');
            if ($path === 'cron/delete') return "Deleted cron job: " . $request->input('command');
            if ($path === 'cron/run') return "Manually ran cron job: " . $request->input('command');
        }

        if (str_starts_with($path, 'supervisor')) {
            if ($path === 'supervisor/create') return "Created supervisor process: " . $request->input('name');
            if ($path === 'supervisor/delete') return "Deleted supervisor process: " . $request->input('name');
            if ($path === 'supervisor/start') return "Started supervisor process: " . $request->input('name');
            if ($path === 'supervisor/stop') return "Stopped supervisor process: " . $request->input('name');
            if ($path === 'supervisor/restart') return "Restarted supervisor process: " . $request->input('name');
            if ($path === 'supervisor/update') return "Updated supervisor process config: " . $request->input('name');
            if ($path === 'supervisor/reload') return "Reloaded supervisor configuration";
            if ($path === 'supervisor/start-all') return "Started all supervisor processes";
            if ($path === 'supervisor/stop-all') return "Stopped all supervisor processes";
            if ($path === 'supervisor/restart-all') return "Restarted all supervisor processes";
        }

        if (str_starts_with($path, 'ssl')) {
            if ($path === 'ssl/install') return "Installed SSL certificate for: " . $request->input('domain');
            if ($path === 'ssl/renew') return "Renewed SSL certificate for: " . $request->input('domain');
            if ($path === 'ssl/remove') return "Removed SSL certificate for: " . $request->input('domain');
            if ($path === 'ssl/renew-all') return "Triggered global SSL certificate renewal";
        }

        if (str_starts_with($path, 'email')) {
            if ($path === 'email/domain/enable') return "Enabled email domain: " . $request->input('domain');
            if ($path === 'email/domain/disable') return "Disabled email domain: " . $request->input('domain');
            if ($path === 'email/account/create') return "Created email account: " . $request->input('username') . "@" . $request->input('domain');
            if ($path === 'email/account/delete') return "Deleted email account: " . $request->input('email');
            if ($path === 'email/account/password') return "Updated password for email account: " . $request->input('email');
            if ($path === 'email/account/quota') return "Updated storage quota for email account: " . $request->input('email');
            if ($path === 'email/alias/create') return "Created email alias: " . $request->input('source') . " -> " . $request->input('destination');
            if ($path === 'email/alias/delete') return "Deleted email alias";
            if ($path === 'email/webmail-login') return "Generated Single Sign-On (SSO) webmail login for: " . $request->input('email');
        }

        if (str_starts_with($path, 'file-manager')) {
            // Path is of format file-manager/{domain}/action
            $segments = explode('/', $path);
            $domain = $segments[1] ?? 'unknown';
            $actionName = $segments[2] ?? '';

            if ($actionName === 'create-file') return "Created file in {$domain}: " . $request->input('path');
            if ($actionName === 'create-directory') return "Created directory in {$domain}: " . $request->input('path');
            if ($actionName === 'save') return "Saved file content in {$domain}: " . $request->input('path');
            if ($actionName === 'rename') return "Renamed file in {$domain} from '" . $request->input('oldPath') . "' to '" . $request->input('newPath') . "'";
            if ($actionName === 'delete') return "Deleted file in {$domain}: " . $request->input('path');
            if ($actionName === 'upload') return "Uploaded file in {$domain} to path: " . $request->input('path');
            if ($actionName === 'chmod') return "Changed file permissions in {$domain}: " . $request->input('path') . " to " . $request->input('mode');
            if ($actionName === 'zip') return "Zipped directory in {$domain}: " . $request->input('path');
            if ($actionName === 'extract') return "Extracted archive in {$domain}: " . $request->input('path');
        }

        if (str_starts_with($path, 'profile')) {
            if ($path === 'profile/update') return "Updated profile information";
            if ($path === 'profile/password') return "Updated account password";
        }

        if (str_starts_with($path, 'users')) {
            if ($action === 'create') return "Created panel user: " . $request->input('email');
            if ($action === 'update') return "Updated user settings for ID: " . $request->route('id');
            if ($action === 'delete') return "Deleted user ID: " . $request->route('id');
        }

        if (str_starts_with($path, 'settings')) {
            if ($path === 'settings/update') return "Updated panel system settings";
            if ($path === 'settings/license/sync') return "Synced panel license with VMCoreCentral";
            if ($path === 'settings/license/deactivate') return "Deactivated panel license";
        }

        if (str_starts_with($path, 'shield')) {
            if ($path === 'shield/scan') return "Started local security threat scan";
            if ($path === 'shield/quarantine') return "Quarantined threat: " . $request->input('path');
            if ($path === 'shield/firewall/add') return "Added firewall rule for: " . $request->input('ip');
            if ($path === 'shield/firewall/delete') return "Removed firewall rule ID: " . $request->input('id');
            if ($path === 'shield/fail2ban/ban') return "Banned IP via Fail2Ban: " . $request->input('ip');
            if ($path === 'shield/fail2ban/unban') return "Unbanned IP: " . $request->input('ip');
        }

        if (str_starts_with($path, 'deployments')) {
            if ($action === 'create') return "Created git deployment config for: " . $request->input('domain');
            if ($action === 'delete') return "Deleted git deployment config ID: " . $request->route('id');
            if ($path === 'deployments/blacklist') return "Updated command blacklist rules";
        }

        return $fallback;
    }
}
