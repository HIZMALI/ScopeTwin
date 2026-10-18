<?php

namespace Tests\Feature;

use App\Domain\PolicyEvaluator;
use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\AuditEvent;
use App\Models\Policy;
use App\Models\PolicyDecision;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Services\AgentKeys;
use App\Services\OrganizationContext;
use App\Services\PolicySnapshots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScopeTwinTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $org;

    private Agent $agent;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->org = OrganizationContext::membership($this->owner)->organization_id;
        $this->agent = Agent::create(['organization_id' => $this->org, 'name' => 'Support Agent', 'status' => 'ACTIVE']);
        $this->token = AgentKeys::issue($this->agent);
    }

    private function policy(string $effect = 'ALLOW', array $conditions = ['action_name' => ['eq' => 'customer.read']], string $reason = 'CUSTOMER_READ_ALLOWED', bool $published = true, ?string $org = null): Policy
    {
        $policy = Policy::create(['organization_id' => $org ?? $this->org, 'name' => 'Rule', 'status' => 'ACTIVE']);
        $v = PolicyVersion::create(['organization_id' => $policy->organization_id, 'policy_id' => $policy->id, 'version' => 1, 'state' => $published ? 'PUBLISHED' : 'DRAFT', 'conditions' => $conditions, 'effect' => $effect, 'reason_code' => $reason, 'published_at' => $published ? now() : null]);
        if ($published) {
            $policy->update(['published_version_id' => $v->id]);
        }

        return $policy;
    }

    private function evaluate(array $body = ['action_name' => 'customer.read'], string $key = 'request-1', ?string $token = null)
    {
        return $this->postJson('/api/v1/agent/actions/evaluate', $body, ['Authorization' => 'Bearer '.($token ?? $this->token), 'Idempotency-Key' => $key]);
    }

    public function test_registration_provisions_owner_and_scoped_dashboard(): void
    {
        $this->post('/register', ['name' => 'New Owner', 'email' => 'owner@example.test', 'password' => 'a-testing-password', 'password_confirmation' => 'a-testing-password'])->assertRedirect('/dashboard');
        $membership = OrganizationContext::membership(User::where('email', 'owner@example.test')->firstOrFail());
        $this->assertSame('OWNER', $membership->role);
        $this->assertNotSame($this->org, $membership->organization_id);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->component('scope/overview')->where('counts.total', 0));
    }

    public function test_token_is_hashed_authenticates_and_is_never_redisplayed_or_stored_raw(): void
    {
        $res = $this->actingAs($this->owner)->postJson('/agents', ['name' => 'New Agent'])->assertCreated();
        $token = $res->json('token');
        $id = $res->json('agent_id');
        $key = AgentApiKey::where('agent_id', $id)->firstOrFail();
        $this->assertSame(AgentKeys::hash($token), $key->key_hash);
        $this->get('/agents/'.$id)->assertOk()->assertDontSee($token)->assertDontSee($key->key_hash);
        $this->evaluate(token: $token)->assertOk()->assertJsonPath('decision', 'DENY');
        foreach (['agent_api_keys', 'sessions', 'audit_events', 'action_requests'] as $table) {
            $this->assertStringNotContainsString($token, DB::table($table)->get()->toJson());
        }
    }

    #[DataProvider('demoActions')]
    public function test_demo_rules_and_money_boundaries(array $body, string $decision, string $reason): void
    {
        $this->artisan('scope:demo-policies', ['--organization' => $this->org])->assertSuccessful();
        $this->evaluate($body)->assertOk()->assertJsonPath('decision', $decision)->assertJsonPath('reason_code', $reason)->assertJsonPath('approval_required', $decision === 'REVIEW');
        $this->assertDatabaseCount('action_requests', 1);
        $this->assertDatabaseCount('policy_decisions', 1);
    }

    public static function demoActions(): array
    {
        return [[['action_name' => 'customer.read'], 'ALLOW', 'CUSTOMER_READ_ALLOWED'],
            [['action_name' => 'refund.issue', 'amount_minor' => 12000, 'currency' => 'USD'], 'ALLOW', 'REFUND_WITHIN_LIMIT'],
            [['action_name' => 'refund.issue', 'amount_minor' => 50000, 'currency' => 'USD'], 'ALLOW', 'REFUND_WITHIN_LIMIT'],
            [['action_name' => 'refund.issue', 'amount_minor' => 50001, 'currency' => 'USD'], 'REVIEW', 'REFUND_REQUIRES_APPROVAL'],
            [['action_name' => 'refund.issue', 'amount_minor' => 240000, 'currency' => 'USD'], 'REVIEW', 'REFUND_REQUIRES_APPROVAL'],
            [['action_name' => 'unknown.action'], 'DENY', 'DEFAULT_DENY'],
            [['action_name' => 'data.export', 'destination_domain' => 'BLOCKED.EXAMPLE'], 'DENY', 'EXPORT_DOMAIN_BLOCKED'],
            [['action_name' => 'data.export', 'destination_domain' => 'other.example'], 'DENY', 'DEFAULT_DENY'],
            [['action_name' => 'refund.issue', 'amount_minor' => 100, 'currency' => 'EUR'], 'DENY', 'DEFAULT_DENY']];
    }

    public function test_matching_deny_overrides_review_and_allow(): void
    {
        $this->policy();
        $this->policy('REVIEW');
        $this->policy('DENY', reason: 'EXPLICIT_BLOCK');
        $this->evaluate()->assertOk()->assertJsonPath('decision', 'DENY')->assertJsonPath('reason_code', 'EXPLICIT_BLOCK');
    }

    public function test_idempotency_returns_original_after_policy_change_and_conflicts_on_different_payload(): void
    {
        $this->policy();
        $first = $this->evaluate()->assertOk()->json();
        $this->policy('DENY');
        $this->evaluate()->assertExactJson($first);
        $this->evaluate(['action_name' => 'another.action'])->assertStatus(409)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('action_requests', 1);
        $this->assertDatabaseCount('policy_decisions', 1);
        $this->assertSame(1, AuditEvent::where('event_type', 'action.evaluated')->count());
    }

    public function test_domain_normalization_key_order_and_free_text_redaction(): void
    {
        $first = $this->evaluate(['action_name' => 'data.export', 'destination_domain' => 'BLOCKED.EXAMPLE', 'resource_label' => 'discard me'])->assertOk()->json();
        $this->evaluate(['destination_domain' => 'blocked.example', 'action_name' => 'data.export'])->assertExactJson($first);
        $this->assertStringNotContainsString('discard me', ActionRequest::first()->toJson());
    }

    #[DataProvider('invalidActions')]
    public function test_invalid_actions_have_422_without_records(array $body): void
    {
        $this->evaluate($body)->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseCount('action_requests', 0);
        $this->assertDatabaseCount('policy_decisions', 0);
    }

    public static function invalidActions(): array
    {
        return [[[]], [['action_name' => str_repeat('x', 129)]], [['action_name' => ['eq' => 'customer.read']]],
            [['action_name' => 'refund.issue', 'amount_minor' => -1, 'currency' => 'USD']],
            [['action_name' => 'refund.issue', 'amount_minor' => 12.5, 'currency' => 'USD']],
            [['action_name' => 'refund.issue', 'amount_minor' => '12000', 'currency' => 'USD']],
            [['action_name' => 'refund.issue', 'amount_minor' => 100000000001, 'currency' => 'USD']],
            [['action_name' => 'refund.issue', 'amount_minor' => 12, 'currency' => 'ZZZ']],
            [['action_name' => 'refund.issue', 'amount_minor' => 12]],
            [['action_name' => 'data.export', 'destination_domain' => 'https://bad.example/path']],
            [['action_name' => 'customer.read', 'organization_id' => 'tenant-override']],
            [['action_name' => 'customer.read', 'nested' => ['token' => 'unsafe']]],
            [['action_name' => 'customer.read', 'resource_label' => str_repeat('x', 81)]], [['action_name' => 'customer.read', 'currency' => null]]];
    }

    public function test_invalid_json_float_integer_and_idempotency_headers(): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'HTTP_IDEMPOTENCY_KEY' => 'float'];
        foreach (['{"action_name":"refund.issue","amount_minor":12000.0,"currency":"USD"}', '{broken', '[]'] as $body) {
            $this->call('POST', '/api/v1/agent/actions/evaluate', [], [], [], $headers, $body)->assertUnprocessable();
        }
        $this->evaluate(key: '')->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->evaluate(key: 'contains spaces')->assertUnprocessable();
        $this->assertDatabaseCount('policy_decisions', 0);
    }

    public function test_invalid_revoked_and_disabled_keys_cannot_persist_decisions(): void
    {
        $this->postJson('/api/v1/agent/actions/evaluate', ['action_name' => 'customer.read'])->assertUnauthorized();
        $this->evaluate(token: 'not-a-key')->assertUnauthorized();
        $this->evaluate(token: substr($this->token, 0, -1).(str_ends_with($this->token, 'a') ? 'b' : 'a'))->assertUnauthorized();
        AgentApiKey::where('agent_id', $this->agent->id)->update(['revoked_at' => now()]);
        $this->evaluate()->assertUnauthorized();
        $this->token = AgentKeys::issue($this->agent);
        $this->agent->update(['status' => 'DISABLED']);
        $this->evaluate()->assertUnauthorized();
        $this->assertDatabaseCount('policy_decisions', 0);
    }

    public function test_draft_publish_edit_and_history_immutability(): void
    {
        $this->actingAs($this->owner);
        $body = ['name' => 'Read rule', 'effect' => 'ALLOW', 'reason_code' => 'READ_ALLOWED', 'conditions' => ['action_name' => ['eq' => 'customer.read']]];
        $this->post('/policies', $body)->assertRedirect();
        $policy = Policy::firstOrFail();
        $v1 = $policy->versions()->firstOrFail();
        $this->evaluate(key: 'draft')->assertJsonPath('reason_code', 'DEFAULT_DENY');
        $this->post('/policies/'.$policy->id.'/publish', ['draft_id' => $v1->id])->assertRedirect();
        $old = $this->evaluate(key: 'published')->assertJsonPath('decision', 'ALLOW')->json('decision_id');
        $body['effect'] = 'DENY';
        $body['reason_code'] = 'READ_BLOCKED';
        $this->put('/policies/'.$policy->id, $body)->assertRedirect();
        $v2 = $policy->versions()->firstOrFail();
        $this->assertSame(2, $v2->version);
        $this->evaluate(key: 'new-draft')->assertJsonPath('decision', 'ALLOW');
        $this->post('/policies/'.$policy->id.'/publish', ['draft_id' => $v2->id])->assertRedirect();
        $this->evaluate(key: 'new-published')->assertJsonPath('decision', 'DENY');
        $this->assertSame('ALLOW', $v1->fresh()->effect);
        $this->assertSame($v1->id, PolicyDecision::find($old)->policy_version_id);
        $this->post('/policies/'.$policy->id.'/publish', ['draft_id' => $v1->id])->assertStatus(409);
        $this->delete('/policies/'.$policy->id)->assertRedirect();
        $this->evaluate(key: 'disabled')->assertJsonPath('reason_code', 'DEFAULT_DENY');
    }

    #[DataProvider('invalidConditions')]
    public function test_unsafe_dsl_is_rejected(mixed $conditions): void
    {
        $this->actingAs($this->owner)->postJson('/policies', ['name' => 'Unsafe', 'effect' => 'ALLOW', 'reason_code' => 'UNSAFE', 'conditions' => $conditions])->assertUnprocessable()->assertJsonValidationErrors('conditions');
        $this->assertDatabaseCount('policies', 0);
    }

    public static function invalidConditions(): array
    {
        return [[[]], [['action_name' => ['regex' => '.*']]], [['action_name' => ['eq' => 'customer.read'], 'amount_minor' => ['lte' => '50000']]], [['action_name' => ['eq' => 'customer.read'], 'organization_id' => ['eq' => 'x']]], [['action_name' => ['eq' => 'customer.read'], 'amount_minor' => ['lte' => 50000, 'gte' => 0]]], [['action_name' => ['in' => []]]], [['action_name' => ['eq' => ['customer.read']]]], [['currency' => ['eq' => 'USD']]], [['action_name' => ['eq' => 'customer.read'], 'destination_domain' => ['gt' => 'a.example']]]];
    }

    public function test_two_organization_isolation_of_lists_reads_writes_publish_keys_and_audit(): void
    {
        $other = User::factory()->create();
        $otherOrg = OrganizationContext::membership($other)->organization_id;
        $otherAgent = Agent::create(['organization_id' => $otherOrg, 'name' => 'Private Agent', 'status' => 'ACTIVE']);
        $otherToken = AgentKeys::issue($otherAgent);
        $otherKey = $otherAgent->keys()->firstOrFail();
        $policy = $this->policy(org: $otherOrg);
        $decision = $this->evaluate(token: $otherToken)->assertJsonPath('decision', 'ALLOW')->json('decision_id');
        $audit = AuditEvent::forOrganization($otherOrg)->firstOrFail();
        $this->actingAs($this->owner);
        $this->get('/agents')->assertInertia(fn (Assert $p) => $p->has('agents', 1)->where('agents.0.id', $this->agent->id));
        $this->get('/policies')->assertInertia(fn (Assert $p) => $p->has('policies', 0));
        $this->get('/decisions')->assertInertia(fn (Assert $p) => $p->has('decisions.data', 0));
        foreach (['/agents/'.$otherAgent->id, '/policies/'.$policy->id, '/decisions/'.$decision, '/audit-events/'.$audit->id] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->patch('/agents/'.$otherAgent->id, ['name' => 'Attack', 'status' => 'DISABLED'])->assertNotFound();
        $this->postJson('/agents/'.$otherAgent->id.'/keys')->assertNotFound();
        $this->delete('/agents/'.$otherAgent->id.'/keys/'.$otherKey->id)->assertNotFound();
        $this->delete('/agents/'.$this->agent->id.'/keys/'.$otherKey->id)->assertNotFound();
        $this->delete('/policies/'.$policy->id)->assertNotFound();
        $this->put('/policies/'.$policy->id, ['name' => 'Attack', 'effect' => 'ALLOW', 'reason_code' => 'ATTACK', 'conditions' => ['action_name' => ['eq' => 'customer.read']]])->assertNotFound();
        $this->post('/policies/'.$policy->id.'/publish', ['draft_id' => $policy->published_version_id])->assertNotFound();
        $this->evaluate(key: 'own')->assertJsonPath('decision', 'DENY');
        $this->evaluate(['action_name' => 'customer.read', 'organization_id' => $otherOrg], 'override')->assertUnprocessable();
        $this->assertNull($otherKey->fresh()->revoked_at);
        $this->assertSame('ACTIVE', $otherAgent->fresh()->status);
        $this->assertSame([], PolicySnapshots::published($this->org));
    }

    public function test_viewer_reads_but_cannot_manage_agents_keys_or_policies(): void
    {
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->update(['role' => 'VIEWER']);
        $policy = $this->policy(published: false);
        $this->actingAs($this->owner);
        foreach (['/dashboard', '/agents', '/policies', '/decisions', '/policies/'.$policy->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->postJson('/agents', ['name' => 'Attack'])->assertForbidden();
        $this->postJson('/agents/'.$this->agent->id.'/keys')->assertForbidden();
        $this->patch('/agents/'.$this->agent->id, ['name' => 'Attack', 'status' => 'ACTIVE'])->assertForbidden();
        $this->delete('/agents/'.$this->agent->id.'/keys/'.$this->agent->keys()->first()->id)->assertForbidden();
        $this->get('/policies/create')->assertForbidden();
        $this->post('/policies', [])->assertForbidden();
        $this->put('/policies/'.$policy->id, [])->assertForbidden();
        $this->post('/policies/'.$policy->id.'/publish', ['draft_id' => $policy->versions()->first()->id])->assertForbidden();
        $this->delete('/policies/'.$policy->id)->assertForbidden();
    }

    public function test_owner_revokes_and_disables_preserving_history(): void
    {
        $this->evaluate()->assertOk();
        $this->actingAs($this->owner)->delete('/agents/'.$this->agent->id.'/keys/'.$this->agent->keys()->first()->id)->assertRedirect();
        $this->evaluate(key: 'revoked')->assertUnauthorized();
        $this->token = $this->postJson('/agents/'.$this->agent->id.'/keys')->assertCreated()->json('token');
        $this->patch('/agents/'.$this->agent->id, ['name' => 'Renamed', 'status' => 'DISABLED'])->assertRedirect();
        $this->evaluate(key: 'disabled')->assertUnauthorized();
        $this->assertDatabaseCount('policy_decisions', 1);
        $this->assertSame(0, $this->agent->keys()->whereNull('revoked_at')->count());
    }

    public function test_snapshot_re_evaluation_has_no_network_or_persistence_side_effects(): void
    {
        Http::preventStrayRequests();
        $this->policy();
        $this->evaluate()->assertOk();
        $before = [ActionRequest::count(), PolicyDecision::count(), AuditEvent::count()];
        $snapshot = PolicySnapshots::published($this->org);
        $input = ActionRequest::first()->normalized_request_json;
        $engine = new PolicyEvaluator;
        $this->assertSame($engine->evaluate($input, $snapshot), $engine->evaluate($input, $snapshot));
        $this->assertSame($before, [ActionRequest::count(), PolicyDecision::count(), AuditEvent::count()]);
        Http::assertNothingSent();
    }

    public function test_rate_limited_request_has_json_429_and_no_extra_decision(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->evaluate(key: 'rate-'.$i)->assertOk();
        }
        $this->evaluate(key: 'rate-61')->assertStatus(429)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('policy_decisions', 60);
    }

    public function test_demo_seed_is_explicit_idempotent_and_blocked_in_production(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('policies', 0);
        $this->artisan('scope:demo-policies', ['--organization' => $this->org])->assertSuccessful();
        $this->artisan('scope:demo-policies', ['--organization' => $this->org])->assertSuccessful();
        $this->assertDatabaseCount('policies', 4);
        $this->app->instance('env', 'production');
        $this->artisan('scope:demo-policies', ['--organization' => $this->org])->assertFailed();
        $this->assertDatabaseCount('policies', 4);
    }

    public function test_admin_can_manage_and_missing_membership_fails_closed(): void
    {
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->update(['role' => 'ADMIN']);
        $this->actingAs($this->owner)->postJson('/agents', ['name' => 'Admin agent'])->assertCreated();
        $policy = $this->policy(published: false);
        $this->post('/policies/'.$policy->id.'/publish', ['draft_id' => $policy->versions()->first()->id])->assertRedirect();
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->delete();
        $this->get('/dashboard')->assertForbidden();
        $this->postJson('/agents', ['name' => 'Orphan agent'])->assertForbidden();
    }

    public function test_idempotency_is_scoped_per_agent_and_decisions_can_be_filtered_and_paginated(): void
    {
        $this->evaluate()->assertOk();
        $secondAgent = Agent::create(['organization_id' => $this->org, 'name' => 'Second Agent', 'status' => 'ACTIVE']);
        $other = AgentKeys::issue($secondAgent);
        $this->evaluate(token: $other)->assertOk();
        $this->assertDatabaseCount('policy_decisions', 2);
        for ($i = 0; $i < 15; $i++) {
            $this->evaluate(['action_name' => 'other.action'], 'page-'.$i)->assertOk();
        }
        $this->actingAs($this->owner)->get('/decisions?page=2')->assertInertia(fn (Assert $p) => $p->has('decisions.data', 2)->where('decisions.total', 17));
        $this->get('/decisions?action=customer.read&decision=DENY')->assertInertia(fn (Assert $p) => $p->has('decisions.data', 2));
        $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('counts.total', 17)->where('counts.deny', 17));
    }

    public function test_published_model_snapshots_cannot_be_overwritten_or_deleted(): void
    {
        $policy = $this->policy();
        $version = $policy->versions()->firstOrFail();
        try {
            $version->update(['effect' => 'DENY']);
            $this->fail('Published snapshot mutation was accepted.');
        } catch (\LogicException $e) {
            $this->assertSame('ALLOW', $version->fresh()->effect);
        }
        $this->expectException(\LogicException::class);
        $version->delete();
    }

    public function test_malformed_domain_identifiers_are_404_instead_of_database_errors(): void
    {
        $this->actingAs($this->owner);
        foreach (['/agents/not-a-uuid', '/policies/not-a-uuid', '/decisions/not-a-uuid', '/audit-events/not-a-uuid'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->deleteJson('/agents/'.$this->agent->id.'/keys/not-a-uuid')->assertNotFound();
    }
}
