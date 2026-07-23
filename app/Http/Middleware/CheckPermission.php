<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Check if user is root/admin or has the specified permission across their assigned websites.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('auth.login');
        }

        // Root and Admin bypass module permission checks
        if ($user->isRootOrAdmin()) {
            return $next($request);
        }

        // Gather all permissions assigned to this user across their websites
        $userPermissions = [];
        $websites = $user->websites ?? [];
        foreach ($websites as $site) {
            foreach (($site->permissions ?? []) as $perm) {
                if (!in_array($perm, $userPermissions)) {
                    $userPermissions[] = $perm;
                }
            }
        }

        if (!in_array($permission, $userPermissions)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => "Access denied. You do not have permission to access '{$permission}'."], 403);
            }
            abort(403, "Access denied. You do not have permission to access '{$permission}'.");
        }

        return $next($request);
    }
}
