<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Usage: middleware('role:root') or middleware('role:root,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('auth.login');
        }

        if ($user->status === 'suspended') {
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('auth.login')->withErrors([
                'email' => 'Your account has been suspended.',
            ]);
        }

        if (!in_array($user->role, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized. Insufficient permissions.'], 403);
            }
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
