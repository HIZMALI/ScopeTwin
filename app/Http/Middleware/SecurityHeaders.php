<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $response = $next($request);
        if (app()->isProduction() && config('security.observe_proxy') && $request->is('ready')) {
            Log::warning('Deployment TLS observation', ['deployment_event' => 'tls_state',
                'secure' => $request->isSecure(), 'hsts_configured' => (bool) config('security.hsts'),
                'proxy_trusted' => $request->isFromTrustedProxy(),
                'forwarded_https' => $request->headers->get('x-forwarded-proto') === 'https']);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cache-Control', 'no-store, private');
        if (! Vite::isRunningHot()) {
            $nonce = Vite::cspNonce();
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
        }
        if (app()->isProduction() && config('security.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
