<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Don't apply to setup routes if not complete
        if ($request->is('setup*')) {
            return $next($request);
        }

        $ip = $request->ip();
        
        // Always allow local access for safety (optional, but recommended for panel management)
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return $next($request);
        }

        try {
            $mode = \App\Models\Setting::where('key', 'ip_restriction_mode')->first()?->value ?? 'off';

            if ($mode === 'off') {
                return $next($request);
            }

            if ($mode === 'whitelist') {
                $rules = \App\Models\SecurityRule::where('type', 'allow')
                    ->where('is_active', true)
                    ->get();
                
                $allowed = false;
                foreach ($rules as $rule) {
                    if ($this->ipMatches($ip, $rule->ip_address)) {
                        $allowed = true;
                        break;
                    }
                }
                
                if (!$allowed) {
                    abort(403, 'Your IP address (' . $ip . ') is not authorized to access this panel.');
                }
            } elseif ($mode === 'blacklist') {
                $rules = \App\Models\SecurityRule::where('type', 'block')
                    ->where('is_active', true)
                    ->get();
                
                foreach ($rules as $rule) {
                    if ($this->ipMatches($ip, $rule->ip_address)) {
                        abort(403, 'Your IP address (' . $ip . ') has been blocked.');
                    }
                }
            }
        } catch (\Exception $e) {
            // If DB is down or table doesn't exist, we allow access to avoid locking out during migration/issues
            \Illuminate\Support\Facades\Log::error('SecurityMiddleware error: ' . $e->getMessage());
        }

        return $next($request);
    }

    /**
     * Check if an IP matches a rule (supports exact match and CIDR)
     */
    private function ipMatches($ip, $ruleIp)
    {
        if ($ip === $ruleIp) {
            return true;
        }

        if (str_contains($ruleIp, '/')) {
            [$subnet, $bits] = explode('/', $ruleIp);
            $ipAddr = ip2long($ip);
            $subnetAddr = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnetAddr &= $mask;
            return ($ipAddr & $mask) == $subnetAddr;
        }

        return false;
    }
}
