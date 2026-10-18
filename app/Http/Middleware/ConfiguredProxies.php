<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

final class ConfiguredProxies extends TrustProxies
{
    public function handle(Request $request, Closure $next)
    {
        $this->proxies = config('security.trusted_proxies');

        return parent::handle($request, $next);
    }
}
