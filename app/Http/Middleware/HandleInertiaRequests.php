<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = [];
        $assignedDomains = [];

        if ($user) {
            // Gather unique permissions across all assigned websites
            $websites = $user->websites ?? collect();
            foreach ($websites as $site) {
                $assignedDomains[] = $site->domain;
                foreach (($site->permissions ?? []) as $perm) {
                    if (!in_array($perm, $permissions)) {
                        $permissions[] = $perm;
                    }
                }
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'root',
                    'linux_user' => $user->linux_user,
                    'is_root' => $user->role === 'root' || $user->id === 1,
                    'permissions' => $user->isRoot() ? ['files','deployments','wordpress','database','ssl','dns','nginx','supervisor','cron'] : $permissions,
                    'assigned_domains' => $user->isRoot() ? [] : $assignedDomains,
                ] : null,
            ],
            'license_warning' => \App\Support\LicenseGuard::shouldShowWarning()
                ? \App\Support\LicenseGuard::warningMessage()
                : null,
        ];
    }
}
