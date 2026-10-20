<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfiguredProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ManagedHostingTest extends TestCase
{
    public function test_https_and_client_ip_require_an_explicit_observed_proxy_and_logs_exclude_forwarded_input(): void
    {
        $this->app->instance('env', 'production');
        config(['security.observe_proxy' => true, 'security.trusted_proxies' => []]);
        Log::spy();
        $request = fn () => Request::create('http://scope.example/ready', 'GET', server: [
            'REMOTE_ADDR' => '10.23.4.5', 'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.3', 'HTTP_AUTHORIZATION' => 'Bearer private',
            'HTTP_X_FORWARDED_HOST' => 'attacker.example',
        ]);
        $next = fn ($request) => response()->json(['secure' => $request->isSecure(), 'ip' => $request->ip()]);
        try {
            $first = (new ConfiguredProxies)->handle($request(), $next)->getData(true);
            $this->assertSame(['secure' => false, 'ip' => '10.23.4.5'], $first);
            config(['security.trusted_proxies' => ['10.23.4.5']]);
            $second = (new ConfiguredProxies)->handle($request(), $next)->getData(true);
            $this->assertSame(['secure' => true, 'ip' => '198.51.100.3'], $second);
            $host = (new ConfiguredProxies)->handle($request(), fn ($request) => response($request->getHost()))->getContent();
            $this->assertSame('scope.example', $host);
            Log::shouldHaveReceived('warning')->with('Deployment proxy observation', ['deployment_event' => 'proxy_peer', 'proxy_peer' => '10.23.4.5'])->times(3);
            Log::shouldHaveReceived('warning')->with('Encrypted deployment peer observation', \Mockery::on(fn ($context) => $context['deployment_event'] === 'proxy_peer_encrypted'
                && ! str_contains($context['peer_cipher'], '10.23.4.5')
                && Crypt::decryptString($context['peer_cipher']) === '10.23.4.5'
            ))->times(3);
        } finally {
            Request::setTrustedProxies([], 0);
        }
    }
}
