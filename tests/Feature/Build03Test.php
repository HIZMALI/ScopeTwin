<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfiguredProxies;
use App\Logging\RedactSecrets;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Build03Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    private function demo(): void
    {
        config(['demo.enabled' => true, 'demo.allow_setup' => true]);
        $this->artisan('scope:judge-demo', ['--confirm-disposable' => true])->assertSuccessful();
    }

    private function state(): array
    {
        return collect(['users', 'organizations', 'organization_memberships', 'agents', 'agent_api_keys', 'policies', 'policy_versions', 'action_requests', 'policy_decisions', 'audit_events', 'approval_requests', 'replay_runs', 'replay_results', 'sessions', 'cache'])
            ->mapWithKeys(fn ($t) => [$t => hash('sha256', DB::table($t)->orderBy($t === 'cache' ? 'key' : 'id')->get()->toJson())])->all();
    }

    public function test_landing_and_disabled_demo_are_stateless_and_honest(): void
    {
        $before = $this->state();
        $this->get('/')->assertOk()->assertViewHas('payload', ['page' => 'landing'])->assertCookieMissing(config('session.cookie'));
        $this->get('/demo')->assertOk()->assertViewHas('payload', ['page' => 'demo', 'demo' => null])->assertCookieMissing(config('session.cookie'));
        $this->assertSame($before, $this->state());
    }

    public function test_setup_is_opt_in_and_requires_explicit_confirmation(): void
    {
        $this->artisan('scope:judge-demo')->assertFailed();
        config(['demo.enabled' => true, 'demo.allow_setup' => true]);
        $this->artisan('scope:judge-demo')->assertFailed();
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_setup_refuses_an_existing_non_demo_workspace(): void
    {
        User::factory()->create();
        $before = $this->state();
        config(['demo.enabled' => true, 'demo.allow_setup' => true]);
        $this->artisan('scope:judge-demo', ['--confirm-disposable' => true])->assertFailed();
        $this->assertSame($before, $this->state());
    }

    public function test_setup_refuses_a_user_database_even_in_production_with_flags(): void
    {
        config(['demo.enabled' => true, 'demo.allow_setup' => true, 'database.connections.'.config('database.default').'.database' => 'scopetwin']);
        $this->app->instance('env', 'production');
        $this->artisan('scope:judge-demo', ['--confirm-disposable' => true])->assertFailed();
    }

    public function test_setup_is_idempotent_and_stores_actual_engine_results(): void
    {
        $this->demo();
        $before = $this->state();
        $this->artisan('scope:judge-demo', ['--confirm-disposable' => true])->assertSuccessful();
        $this->assertSame($before, $this->state());
        $this->assertDatabaseCount('policy_decisions', 3);
        $this->assertDatabaseCount('replay_results', 3);
        $this->assertDatabaseHas('approval_requests', ['status' => 'APPROVED']);
        $this->assertDatabaseMissing('agent_api_keys', ['revoked_at' => null]);
        $payload = $this->get('/demo')->assertOk()->viewData('payload')['demo'];
        $this->assertSame([3, 2, 2], [$payload['sampleCount'], $payload['changed'], $payload['newlyDenied']]);
        $rows = $payload['rows']->keyBy('amount');
        $this->assertSame(['ALLOW', 'ALLOW', 'DENY'], array_values(array_intersect_key($rows[12000], array_flip(['original', 'baseline', 'candidate']))));
        $this->assertSame(['REVIEW', 'REVIEW', 'DENY'], array_values(array_intersect_key($rows[240000], array_flip(['original', 'baseline', 'candidate']))));
    }

    public function test_public_get_cannot_select_foreign_workspace_or_write_any_table(): void
    {
        $this->demo();
        $foreign = User::factory()->create(['email' => 'do-not-disclose@example.test', 'name' => 'Private tenant identity']);
        $org = Organization::where('id', '!=', config('demo.organization_id'))->firstOrFail();
        $before = $this->state();
        foreach (['/demo', '/demo/replay?organization_id='.$org->id.'&limit=999999&user_id='.$foreign->id] as $url) {
            $response = $this->get($url)->assertOk()->assertCookieMissing(config('session.cookie'));
            $json = json_encode($response->viewData('payload'));
            foreach ([$foreign->email, $foreign->name, $org->id, 'key_hash', 'prefix', 'agent_api_key_id', 'idempotency_key', 'st_'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
        $this->get('/demo/'.$org->id)->assertNotFound();
        $this->postJson('/demo', ['organization_id' => $org->id])->assertStatus(405);
        $this->postJson('/demo/replay')->assertStatus(405);
        $this->assertSame($before, $this->state());
    }

    public function test_public_visitors_cannot_reach_private_workflows(): void
    {
        foreach (['/dashboard', '/agents', '/policies', '/replays', '/approvals', '/usage'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson('/api/v1/agent/actions/evaluate', ['action_name' => 'customer.read'])->assertUnauthorized();
        $this->postJson('/agents', ['name' => 'Unauthorized'])->assertUnauthorized();
        $this->assertDatabaseCount('policy_decisions', 0);
    }

    public function test_security_headers_csp_nonce_no_cache_and_hsts_only_on_secure_production(): void
    {
        $response = $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        preg_match("/nonce-([^']+)/", $response->headers->get('Content-Security-Policy'), $matches);
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
        $response->assertHeaderMissing('Strict-Transport-Security');
        config(['security.hsts' => true]);
        $this->app->instance('env', 'production');
        $this->get('http://127.0.0.1/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://127.0.0.1/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_registration_throttle_ignores_untrusted_forwarded_ips(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/register', [], ['X-Forwarded-For' => '203.0.113.'.$i])->assertUnprocessable();
        }
        $this->postJson('/register', [], ['X-Forwarded-For' => '198.51.100.1'])->assertTooManyRequests();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_throttle_ignores_untrusted_forwarded_ips(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'missing@example.test', 'password' => 'invalid'], ['X-Forwarded-For' => '203.0.113.'.$i])->assertUnprocessable();
        }
        // Starter returns predictable validation errors with lockout text, rather than transport 429.
        $this->postJson('/login', ['email' => 'missing@example.test', 'password' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertTrue(RateLimiter::tooManyAttempts('missing@example.test|127.0.0.1', 5));
    }

    public function test_unconfigured_production_mail_cannot_claim_delivery(): void
    {
        $this->app->instance('env', 'production');
        config(['security.password_recovery' => true, 'mail.default' => 'array']);
        $this->withSession(['_token' => 'smtp-test-csrf'])->postJson('/forgot-password', ['email' => 'nobody@example.test', '_token' => 'smtp-test-csrf'])->assertStatus(503);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_readiness_is_separate_from_liveness_and_does_not_leak_errors(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('private database password'));
        $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_production_readiness_rejects_debug_or_non_https_configuration(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => true]);
        $this->get('/ready')->assertStatus(503);
        config(['app.debug' => false, 'app.url' => 'http://127.0.0.1', 'security.allow_insecure_http' => false]);
        $this->get('/ready')->assertStatus(503);
        config(['app.url' => 'https://127.0.0.1']);
        $this->get('/ready')->assertOk();
    }

    public function test_production_errors_do_not_render_exception_details(): void
    {
        config(['app.debug' => false]);
        $this->app->instance('env', 'production');
        Route::get('/release-test-error', fn () => throw new \RuntimeException('private-diagnostic-marker'));
        $this->get('/release-test-error')->assertStatus(500)->assertDontSee('private-diagnostic-marker')->assertDontSee('Stack trace');
    }

    public function test_log_redaction_removes_nested_credentials_and_exception_messages(): void
    {
        $token = 'st_'.str_repeat('a', 16).'_'.str_repeat('b', 64);
        $clean = RedactSecrets::clean(['nested' => ['authorization' => 'Bearer private', 'email' => 'private@example.test'], 'message' => $token, 'exception' => new \RuntimeException('sensitive SQL binding')]);
        $this->assertSame('[REDACTED]', $clean['nested']['authorization']);
        $this->assertSame('[REDACTED]', $clean['nested']['email']);
        $this->assertSame('[REDACTED]', $clean['message']);
        $this->assertSame(\RuntimeException::class, $clean['exception']);
    }

    public function test_only_explicit_proxy_addresses_may_supply_client_ip_and_https(): void
    {
        $middleware = new ConfiguredProxies;
        $request = fn ($ip) => Request::create('http://localhost/ready', server: ['REMOTE_ADDR' => $ip, 'HTTP_X_FORWARDED_FOR' => '203.0.113.8', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $read = fn ($r) => response()->json(['ip' => $r->ip(), 'secure' => $r->isSecure()]);
        config(['security.trusted_proxies' => []]);
        $this->assertSame(['ip' => '10.0.0.1', 'secure' => false], $middleware->handle($request('10.0.0.1'), $read)->getData(true));
        config(['security.trusted_proxies' => ['10.0.0.1']]);
        $this->assertSame(['ip' => '203.0.113.8', 'secure' => true], $middleware->handle($request('10.0.0.1'), $read)->getData(true));
        $this->assertSame(['ip' => '10.0.0.2', 'secure' => false], $middleware->handle($request('10.0.0.2'), $read)->getData(true));
    }
}
