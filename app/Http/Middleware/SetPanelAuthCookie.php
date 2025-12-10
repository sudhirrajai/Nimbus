<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPanelAuthCookie
{
    /**
     * Set a cookie when user is authenticated that nginx can check
     * to allow access to protected resources like phpMyAdmin
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (auth()->check()) {
            // Set a cookie that nginx can verify
            // This cookie indicates the user is logged into the panel
            $response->cookie(
                'nimbus_auth',
                hash('sha256', auth()->id() . config('app.key')),
                60 * 24, // 24 hours
                '/',
                null,
                false, // secure - set true in production with HTTPS
                false  // httpOnly - false so nginx can read it
            );
        }

        return $response;
    }
}
