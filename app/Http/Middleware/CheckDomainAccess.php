<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDomainAccess
{
    /**
     * Check if the authenticated user has access to the requested domain.
     * Looks for domain in route parameter 'domain', or request input 'domain'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('auth.login');
        }

        // Root users bypass all domain checks
        if ($user->isRoot()) {
            return $next($request);
        }

        // Try to find the domain from the route parameter or request data
        $domain = $request->route('domain')
            ?? $request->input('domain')
            ?? $this->extractDomainFromPath($request);

        if ($domain && !$user->canAccessDomain($domain)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'You do not have access to this website.'], 403);
            }
            abort(403, 'You do not have access to this website.');
        }

        return $next($request);
    }

    /**
     * Try to extract domain from the URL path (e.g., /file-manager/example.com)
     */
    private function extractDomainFromPath(Request $request): ?string
    {
        $path = $request->path();

        // Match patterns like file-manager/{domain}, domains/{domain}
        $patterns = [
            '#^file-manager/([^/]+)#',
            '#^deployments/([^/]+)#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
