<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if allow_ip_access is disabled
        $allowIpAccess = Setting::where('key', 'allow_ip_access')->first()?->value ?? '1';
        
        if ($allowIpAccess === '0') {
            $host = $request->getHost();
            
            // 2. Check if host is an IP address
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $panelDomain = Setting::where('key', 'panel_domain')->first()?->value;
                $panelSsl = Setting::where('key', 'panel_ssl')->first()?->value ?? '1';
                
                // 3. If domain is configured, redirect to it
                if ($panelDomain) {
                    $protocol = $panelSsl === '1' ? 'https' : 'http';
                    return redirect("{$protocol}://{$panelDomain}" . $request->getRequestUri());
                }
                
                // If no domain is configured but IP is disabled (weird state), 
                // we should probably allow it or show a specific error.
                // For safety, if no domain is set, we still allow IP.
            }
        }

        return $next($request);
    }
}
