<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * In production, redirect any insecure request to its HTTPS equivalent
     * and emit a Strict-Transport-Security header so compliant browsers
     * refuse to downgrade subsequent requests. A reverse proxy terminating
     * TLS and forwarding plain HTTP should be configured as a trusted
     * proxy; users who intentionally rely on that pattern can set
     * APP_FORCE_HTTPS=false to disable redirection while still sending
     * HSTS from the edge.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isProduction = app()->environment('production');
        $forceConfigured = (bool) config('app.force_https', $isProduction);

        if ($forceConfigured) {
            URL::forceScheme('https');

            if (! $request->secure()) {
                return redirect()->secure($request->getRequestUri(), 301);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        if ($forceConfigured && ! $response->headers->has('Strict-Transport-Security')) {
            $maxAge = (int) config('app.hsts_max_age', 31_536_000);
            $include = (bool) config('app.hsts_include_subdomains', true);

            $header = "max-age={$maxAge}";
            if ($include) {
                $header .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $header);
        }

        return $response;
    }
}
