<?php

namespace Tests\Feature;

use App\Models\ReplayRun;
use App\Models\User;
use App\Services\ReplayAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplayAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private ReplayRun $run;

    private string $key;

    private string $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['demo.enabled' => true, 'demo.allow_setup' => true]);
        $this->artisan('scope:judge-demo', ['--confirm-disposable' => true])->assertSuccessful();
        $this->owner = User::firstOrFail();
        $this->run = ReplayRun::firstOrFail();
        $this->key = Str::random(48);
        $this->assistant = (string) Str::uuid();
        config(['backboard.enabled' => true, 'backboard.api_key' => $this->key,
            'backboard.assistant_id' => $this->assistant,
            'backboard.llm_provider' => 'mock-provider', 'backboard.model_name' => 'mock-json-model']);
    }

    private function url(): string
    {
        return '/replays/'.$this->run->id.'/advice';
    }

    private function state(): array
    {
        return collect(['users', 'organizations', 'organization_memberships', 'agents', 'agent_api_keys', 'policies', 'policy_versions',
            'action_requests', 'policy_decisions', 'audit_events', 'approval_requests', 'replay_runs', 'replay_results'])
            ->mapWithKeys(fn ($table) => [$table => hash('sha256', DB::table($table)->orderBy('id')->get()->toJson())])->all();
    }

    private function valid(): array
    {
        return ['explanation' => 'The simulated candidate changes some historical outcomes. Human review can examine the differences in the frozen comparison.',
            'questions' => ['Which changed outcomes need closer human review?']];
    }

    private function fake(?array $output = null, int $cleanup = 204): void
    {
        Http::fake(['*/threads/messages' => fn () => Http::response(['status' => 'COMPLETED', 'thread_id' => (string) Str::uuid(), 'assistant_id' => $this->assistant, 'content' => json_encode($output ?? $this->valid())]),
            '*/threads/*' => Http::response('', $cleanup)]);
    }

    public function test_success_is_aggregate_only_cached_and_does_not_change_any_core_record(): void
    {
        $this->fake();
        $before = $this->state();
        $this->actingAs($this->owner)->postJson($this->url())->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('cached', false)
            ->assertJsonPath('advice', $this->valid())->assertJsonPath('cleanup_confirmed', true);
        $this->postJson($this->url())->assertOk()->assertJsonPath('cached', true);
        $this->assertSame($before, $this->state());
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $this->assertSame('https://app.backboard.io/api/threads/messages', $request->url());
            $this->assertTrue($request->hasHeader('X-API-Key', $this->key));
            $summary = json_decode($request['content'], true);
            $this->assertSame(app(ReplayAdvisor::class)->summary($this->run), $summary);
            $this->assertSame(['simulated', 'sample_count', 'transitions', 'changed', 'newly_denied', 'newly_reviewed', 'unchanged'], array_keys($summary));
            foreach ([$this->run->id, $this->run->organization_id, $this->owner->email, $this->key, 'refund.issue', 'USD', '240000'] as $private) {
                $this->assertStringNotContainsString($private, $request->body());
            }
            foreach (['memory' => 'off', 'web_search' => 'off', 'tools' => [], 'stream' => false, 'json_output' => true, 'image_generation' => 'off', 'video_generation' => 'off'] as $key => $value) {
                $this->assertSame($value, $request[$key]);
            }
            $this->assertFalse(isset($request['thread_id']));
            $this->assertTrue(hash_equals($this->assistant, $request['assistant_id']));

            return true;
        });
        $this->assertDatabaseCount('advisor_explanations', 1);
        $this->assertSame(2, (int) DB::table('advisor_quotas')->sum('calls'));
        $page = $this->get('/replays/'.$this->run->id)->assertOk();
        $page->assertDontSee($this->key, false)->assertDontSee('mock-json-model')->assertDontSee($this->assistant, false);
    }

    public static function unavailableConfigurations(): array
    {
        return [['enabled', false, 'disabled'], ['api_key', '', 'not_configured'], ['model_name', '', 'not_configured'], ['llm_provider', '', 'not_configured'],
            ['assistant_id', '', 'not_configured'], ['assistant_id', 'not-a-uuid', 'not_configured'], ['assistant_id', ['unexpected'], 'not_configured'],
            ['assistant_id', 'https://untrusted.example', 'not_configured']];
    }

    #[DataProvider('unavailableConfigurations')]
    public function test_disabled_and_missing_configuration_make_no_calls_or_reservations(string $field, mixed $value, string $status): void
    {
        config(['backboard.'.$field => $value]);
        $this->actingAs($this->owner)->postJson($this->url())->assertStatus(503)->assertJsonPath('status', $status)->assertJsonStructure(['fallback']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('advisor_quotas', 0);
        $this->assertDatabaseCount('advisor_explanations', 0);
    }

    public static function failures(): array
    {
        return [[401], [402], [429], [500], ['timeout'], ['json'], ['long'], ['html'], ['digits'], ['tools'], ['thread'], ['outer'], ['key'], ['questions'], ['assistant_missing'], ['assistant_mismatch']];
    }

    #[DataProvider('failures')]
    public function test_provider_failures_are_sanitized_cached_without_retry_and_leave_core_unchanged(int|string $failure): void
    {
        Log::spy();
        $output = $this->valid();
        if ($failure === 'long') {
            $output['explanation'] = str_repeat('a', 950).'. Another sentence.';
        }
        if ($failure === 'html') {
            $output['explanation'] = '<script>alert()</script>. Another sentence.';
        }
        if ($failure === 'digits') {
            $output['explanation'] = 'There are 3 changes. Another sentence.';
        }
        if ($failure === 'key') {
            $output['explanation'] = $this->key.'. Another sentence.';
        }
        if ($failure === 'questions') {
            $output['questions'] = ['a', 'b', 'c'];
        }
        $outer = ['status' => 'COMPLETED', 'thread_id' => (string) Str::uuid(), 'assistant_id' => $this->assistant, 'content' => $failure === 'json' ? '{bad' : json_encode($output)];
        if ($failure === 'tools') {
            $outer['tool_calls'] = [['name' => 'unrequested']];
        }
        if ($failure === 'thread') {
            unset($outer['thread_id']);
        }
        if ($failure === 'assistant_missing') {
            unset($outer['assistant_id']);
        }
        if ($failure === 'assistant_mismatch') {
            $outer['assistant_id'] = (string) Str::uuid();
        }
        Http::fake(function ($request) use ($failure, $outer) {
            if ($request->method() === 'DELETE') {
                return Http::response('', 204);
            }
            if ($failure === 'timeout') {
                throw new ConnectionException('Untrusted provider details must never be logged');
            }

            return Http::response($failure === 'outer' ? str_repeat('x', 33000) : $outer, is_int($failure) ? $failure : 200);
        });
        $before = $this->state();
        $this->actingAs($this->owner)->postJson($this->url())->assertStatus(502)->assertJsonPath('status', 'unavailable')->assertDontSee($this->key, false);
        $calls = count(Http::recorded());
        $this->postJson($this->url())->assertStatus(502)->assertJsonPath('cached', true);
        Http::assertSentCount($calls);
        $this->assertSame($before, $this->state());
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'Replay advisor' && array_keys($context) === ['advisor_status', 'elapsed_ms'] && is_int($context['elapsed_ms']);
        });
    }

    public function test_cleanup_failure_is_explicit_without_disclosing_provider_details(): void
    {
        $this->fake(cleanup: 500);
        $this->actingAs($this->owner)->postJson($this->url())->assertOk()->assertJsonPath('cleanup_confirmed', false);
        Http::assertSentCount(2);
    }

    public function test_identity_scope_roles_public_get_and_submitted_content_cannot_trigger_calls(): void
    {
        $foreign = User::factory()->create();
        $this->actingAs($foreign)->postJson($this->url())->assertNotFound();
        $this->postJson($this->url(), ['content' => 'untrusted'])->assertNotFound();
        $this->postJson('/replays/'.Str::uuid().'/advice')->assertNotFound();
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->update(['role' => 'VIEWER']);
        $this->actingAs($this->owner)->postJson($this->url())->assertForbidden();
        $this->postJson($this->url(), ['organization_id' => $this->run->organization_id])->assertForbidden();
        $this->get('/replays/'.$this->run->id)->assertOk();
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->update(['role' => 'OWNER']);
        $this->postJson($this->url(), ['content' => 'private content', 'organization_id' => $this->run->organization_id])->assertUnprocessable();
        auth()->forgetGuards();
        $this->postJson($this->url())->assertUnauthorized();
        $before = $this->state();
        $this->get('/demo')->assertOk();
        $this->get('/demo/replay')->assertOk();
        $this->head('/demo/replay')->assertOk();
        $this->postJson('/demo/replay')->assertStatus(405);
        $this->assertSame($before, $this->state());
        Http::assertNothingSent();
        $this->assertDatabaseCount('advisor_quotas', 0);
    }

    public function test_quota_is_utc_attempt_based_hard_capped_and_cached_results_are_free(): void
    {
        $this->fake();
        $this->travelTo(now('UTC')->startOfDay()->addHours(10));
        config(['backboard.daily_calls' => 99, 'backboard.user_calls_per_minute' => 99]);
        for ($i = 0; $i < 3; $i++) {
            if ($i) {
                $this->travel(1)->minutes();
                $this->run->update(['candidate_fingerprint' => hash('sha256', 'frozen'.$i)]);
            }
            $this->actingAs($this->owner)->postJson($this->url())->assertOk();
            $this->postJson($this->url())->assertOk()->assertJsonPath('cached', true);
            $this->run->update(['candidate_fingerprint' => hash('sha256', 'different'.$i)]);
            $this->postJson($this->url())->assertStatus(429)->assertJsonPath('status', 'quota_exhausted');
        }
        $this->travel(1)->days();
        $this->postJson($this->url())->assertOk();
        Http::assertSentCount(8);
    }

    public function test_pending_lease_and_failed_attempt_do_not_start_duplicate_generation(): void
    {
        Http::fake(function ($request) {
            $again = app(ReplayAdvisor::class)->advise($this->owner, $this->run->id);
            $this->assertSame('in_progress', $again['status']);

            return Http::response('', 402);
        });
        $this->actingAs($this->owner)->postJson($this->url())->assertStatus(502);
        $this->postJson($this->url())->assertStatus(502)->assertJsonPath('cached', true);
        Http::assertSentCount(1);
        $this->assertSame(2, (int) DB::table('advisor_quotas')->sum('calls'));
    }

    public function test_invalid_aggregate_fails_closed_without_network(): void
    {
        $this->run->update(['sample_count' => 501]);
        $this->actingAs($this->owner)->postJson($this->url())->assertStatus(502);
        Http::assertNothingSent();
        $this->assertDatabaseCount('advisor_quotas', 0);
    }

    public function test_user_cooldown_spans_calendar_minute_boundary_and_cache_expires(): void
    {
        $this->fake();
        $this->travelTo(now('UTC')->startOfDay()->addHours(10)->addSeconds(59));
        $this->actingAs($this->owner)->postJson($this->url())->assertOk();
        $this->run->update(['candidate_fingerprint' => hash('sha256', 'different-minute')]);
        $this->travel(2)->seconds();
        $this->postJson($this->url())->assertStatus(429);
        $this->travel(59)->seconds();
        $this->postJson($this->url())->assertOk();
        $this->travel(16)->minutes();
        $this->postJson($this->url())->assertOk()->assertJsonPath('cached', false);
        Http::assertSentCount(6);
    }

    public function test_zero_configured_quota_and_live_smoke_without_flags_never_call_provider(): void
    {
        config(['backboard.daily_calls' => 0]);
        $this->actingAs($this->owner)->postJson($this->url())->assertStatus(429);
        $this->artisan('scope:backboard-smoke')->expectsOutput('LIVE_NOT_TESTED')->assertFailed();
        $this->artisan('scope:backboard-smoke', ['--live-backboard-smoke' => true])->expectsOutput('LIVE_NOT_TESTED')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_csrf_is_required_in_real_middleware_mode_and_public_tour_cannot_write_advisor_tables(): void
    {
        $this->app->instance('env', 'production');
        $this->actingAs($this->owner)->withSession(['_token' => Str::random(40)])->postJson($this->url())->assertStatus(419);
        $this->get('/demo')->assertOk();
        $this->get('/demo/replay')->assertOk();
        $this->assertDatabaseCount('advisor_explanations', 0);
        $this->assertDatabaseCount('advisor_quotas', 0);
        Http::assertNothingSent();
    }

    public function test_bound_assistant_is_private_in_responses_cache_and_finite_logs(): void
    {
        Log::spy();
        $this->fake();
        $before = $this->state();
        $response = $this->actingAs($this->owner)->postJson($this->url())->assertOk();
        $privatePage = $this->get('/replays/'.$this->run->id)->assertOk();
        $publicPage = $this->get('/demo/replay')->assertOk();
        $surfaces = [$response->getContent(), $privatePage->getContent(), $publicPage->getContent(), DB::table('advisor_explanations')->value('result_json')];
        foreach ($surfaces as $surface) {
            foreach ([$this->assistant, $this->key] as $private) {
                $this->assertTrue(! str_contains($surface, $private), 'A private configured value must never be exposed.');
            }
        }
        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Replay advisor' && array_keys($context) === ['advisor_status', 'elapsed_ms']
                && $context['advisor_status'] === 'completed' && is_int($context['elapsed_ms']);
        });
        $this->assertSame($before, $this->state());
    }

    public function test_assistant_change_invalidates_cached_advice_and_cannot_bypass_quota(): void
    {
        $this->fake();
        $this->actingAs($this->owner)->postJson($this->url())->assertOk();
        config(['backboard.assistant_id' => strtoupper($this->assistant)]);
        $this->postJson($this->url())->assertOk()->assertJsonPath('cached', true);
        $this->assistant = (string) Str::uuid();
        config(['backboard.assistant_id' => $this->assistant]);
        $this->postJson($this->url())->assertStatus(429);
        Http::assertSentCount(2);
        $this->travel(61)->seconds();
        $this->postJson($this->url())->assertOk()->assertJsonPath('cached', false);
        $this->assertDatabaseCount('advisor_explanations', 2);
    }

    public function test_removing_binding_blocks_even_cached_advice_without_an_upstream_call(): void
    {
        $this->fake();
        $this->actingAs($this->owner)->postJson($this->url())->assertOk();
        config(['backboard.assistant_id' => '']);
        $this->postJson($this->url())->assertStatus(503)->assertJsonPath('status', 'not_configured');
        Http::assertSentCount(2);
        $this->assertSame(2, (int) DB::table('advisor_quotas')->sum('calls'));
    }
}
