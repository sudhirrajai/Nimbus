<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\LicenseService;

class VerifyLicense
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow activation, login, setup, and static routes
        if ($request->is('activate*') || $request->is('login*') || $request->is('logout*') || $request->is('_debugbar*') || $request->is('setup*')) {
            return $next($request);
        }

        $license = $this->licenseService->checkLicense();

        if (!$license['status']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'License required.',
                    'message' => $license['message']
                ], 402);
            }

            return redirect()->route('activate.index')->with('error', $license['message']);
        }

        return $next($request);
    }
}
