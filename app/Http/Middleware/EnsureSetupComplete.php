<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Handle an incoming request.
     * Redirect to setup if no users exist.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (User::count() === 0 && !$request->routeIs('auth.setup', 'auth.setup.store')) {
            return redirect()->route('auth.setup');
        }

        return $next($request);
    }
}
