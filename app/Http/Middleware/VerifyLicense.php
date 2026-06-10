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

        // Fast path: check persistent lock flag BEFORE making any network calls.
        // This flag survives cache clears and code restarts.
        // The only way to clear it is via successful re-activation.
        try {
            if ($this->licenseService->isLocked()) {
                $reason = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'license_lock_reason')
                    ->value('value') ?? 'License verification failed.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'error'   => 'License required.',
                        'message' => $reason,
                    ], 402);
                }

                return redirect()->route('activate.index')->with('error', $reason);
            }
        } catch (\Exception $e) {
            // If DB is unavailable during migration/install, don't block
        }

        $license = $this->licenseService->checkLicense();

        if (!$license['status']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'License required.',
                    'message' => $license['message']
                ], 402);
            }

            return redirect()->route('activate.index')->with('error', $license['message']);
        }

        return $next($request);
    }
}
