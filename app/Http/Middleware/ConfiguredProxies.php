<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

final class ConfiguredProxies extends TrustProxies
{
    // The canonical Host comes from APP_URL/TrustHosts, never a forwarded host/prefix.
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;

    public function handle(Request $request, Closure $next)
    {
        $this->proxies = config('security.trusted_proxies');
        // Temporary operator opt-in: observe the actual peer, never a forwarded header.
        if (app()->isProduction() && config('security.observe_proxy') && $request->is('ready')) {
            $peer = $request->server('REMOTE_ADDR');
            if (is_string($peer) && filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && ! filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                Log::warning('Deployment proxy observation', ['deployment_event' => 'proxy_peer', 'proxy_peer' => $peer]);
            }
            if (is_string($peer) && strlen($peer) <= 128) {
                Log::warning('Encrypted deployment peer observation', ['deployment_event' => 'proxy_peer_encrypted',
                    'peer_cipher' => Crypt::encryptString($peer)]);
            }
        }

        return parent::handle($request, function (Request $request) use ($next) {
            // Opt-in only for an operator-verified Cloudflare edge path. XFF may
            // contain visitor-supplied entries; use the edge-overwritten single IP.
            if (app()->isProduction() && config('security.client_ip_header') === 'CF-Connecting-IP' && $request->isFromTrustedProxy()) {
                $client = $request->headers->get('CF-Connecting-IP');
                $request->headers->remove('X-Forwarded-For');
                if (is_string($client) && filter_var($client, FILTER_VALIDATE_IP)) {
                    $request->headers->set('X-Forwarded-For', $client);
                }
            }

            return $next($request);
        });
    }
}
