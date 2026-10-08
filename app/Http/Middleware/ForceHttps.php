<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redirect insecure requests to their HTTPS equivalent when
     * APP_FORCE_HTTPS is enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https') && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
