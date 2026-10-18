<?php

namespace Tests\Feature;

use App\Domain\SnapshotManifest;
use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Policy;
use App\Models\PolicyDecision;
use App\Models\PolicyVersion;
use App\Models\ReplayRun;
use App\Models\User;
use App\Services\AgentKeys;
use App\Services\OrganizationContext;
use App\Services\PolicySnapshots;
use App\Services\ReplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Build02Test extends TestCase
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
        $this->agent = Agent::create(['organization_id' => $this->org, 'name' => 'Test Agent', 'status' => 'ACTIVE']);
        $this->token = AgentKeys::issue($this->agent);
    }

    private function rule(string $effect = 'DENY', array $conditions = ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => ['gt' => 10000], 'currency' => ['eq' => 'USD']], bool $published = false, ?string $org = null): PolicyVersion
    {
        $policy = Policy::create(['organization_id' => $org ?? $this->org, 'name' => 'Test '.$effect.' policy', 'status' => 'ACTIVE']);
        $version = $policy->versions()->create(['organization_id' => $policy->organization_id, 'version' => 1, 'state' => $published ? 'PUBLISHED' : 'DRAFT', 'conditions' => $conditions, 'effect' => $effect, 'reason_code' => 'TEST_'.$effect, 'published_at' => $published ? now() : null]);
        if ($published) {
            $policy->update(['published_version_id' => $version->id]);
        }

        return $version;
    }

    private function api(array $body = ['action_name' => 'refund.issue', 'amount_minor' => 240000, 'currency' => 'USD'], ?string $key = null, ?string $token = null)
    {
        return $this->postJson('/api/v1/agent/actions/evaluate', $body, ['Authorization' => 'Bearer '.($token ?? $this->token), 'Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function demo(): PolicyVersion
    {
        $this->artisan('scope:demo-policies', ['--organization' => $this->org])->assertSuccessful();
        $this->api(['action_name' => 'refund.issue', 'amount_minor' => 12000, 'currency' => 'USD'])->assertOk();
        $this->api()->assertOk();
        $this->api(['action_name' => 'unknown.action'])->assertOk();

        return $this->rule();
    }

    private function replay(PolicyVersion $draft, array $filters = []): ReplayRun
    {
        return app(ReplayService::class)->create($this->owner, $draft->id, $filters);
    }

    private function pending(): ApprovalRequest
    {
        $this->rule('REVIEW', ['action_name' => ['eq' => 'refund.issue']], true);
        $this->api()->assertOk()->assertJsonPath('decision', 'REVIEW');

        return ApprovalRequest::firstOrFail();
    }

    private function state(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_required_replay_demo_has_three_way_outcomes_and_exactly_two_changes(): void
    {
        $draft = $this->demo();
        Http::preventStrayRequests();
        $tables = ['action_requests', 'policy_decisions', 'policy_versions', 'agents', 'agent_api_keys', 'approval_requests'];
        $before = $this->state($tables);
        $this->actingAs($this->owner)->post('/replays', ['draft_id' => $draft->id])->assertRedirect();
        $run = ReplayRun::firstOrFail();
        $this->assertSame(3, $run->sample_count);
        $this->assertSame(2, $run->summary['changed']);
        $this->assertSame(2, $run->summary['newly_denied']);
        $this->assertSame(1, $run->summary['transitions']['ALLOW_DENY']);
        $this->assertSame(1, $run->summary['transitions']['REVIEW_DENY']);
        $this->assertSame(1, $run->summary['transitions']['DENY_DENY']);
        $small = $run->results()->where('input_snapshot->amount_minor', 12000)->firstOrFail();
        $this->assertSame(['ALLOW', 'ALLOW', 'DENY'], [$small->original_effect, $small->baseline_effect, $small->candidate_effect]);
        $this->assertSame('DRAFT', $small->candidate_outcome['match']['source']);
        $this->assertNull($small->candidate_outcome['match']['policy_version_id']);
        $this->assertSame($draft->id, $small->candidate_outcome['match']['draft_version_id']);
        $this->assertSame($run->candidate_fingerprint, $small->candidate_outcome['match']['fingerprint']);
        $this->assertSame($before, $this->state($tables));
        $this->assertSame('DRAFT', $draft->fresh()->state);
        $this->assertSame(1, AuditEvent::where('event_type', 'replay.completed')->count());
        Http::assertNothingSent();
        $this->get('/replays/'.$run->id.'?changed=1')->assertOk()->assertInertia(fn (Assert $p) => $p->has('results.data', 2));
        $this->get('/replays/'.$run->id.'/results/'.$small->id)->assertInertia(fn (Assert $p) => $p->component('scope/replay-result'));
    }

    public function test_recorded_vs_baseline_difference_is_not_attributed_to_candidate(): void
    {
        $allow = $this->rule('ALLOW', ['action_name' => ['eq' => 'refund.issue']], true);
        $this->api()->assertJsonPath('decision', 'ALLOW');
        $this->rule('REVIEW', ['action_name' => ['eq' => 'refund.issue']], true);
        $draft = $this->rule('ALLOW', ['action_name' => ['eq' => 'refund.issue']]);
        $run = $this->replay($draft);
        $row = $run->results()->firstOrFail();
        $this->assertSame(['ALLOW', 'REVIEW', 'REVIEW'], [$row->original_effect, $row->baseline_effect, $row->candidate_effect]);
        $this->assertSame(0, $run->summary['changed']);
        $this->assertSame($allow->id, $row->original_outcome['match']['policy_version_id']);
    }

    public function test_draft_substitutes_its_own_published_rule_and_other_disabled_rules_stay_absent(): void
    {
        $published = $this->rule('ALLOW', ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => ['lte' => 50000], 'currency' => ['eq' => 'USD']], true);
        $disabled = $this->rule('DENY', ['action_name' => ['eq' => 'refund.issue']], true);
        $disabled->policy->update(['status' => 'DISABLED']);
        $this->api(['action_name' => 'refund.issue', 'amount_minor' => 12000, 'currency' => 'USD'])->assertJsonPath('decision', 'ALLOW');
        $draft = $published->policy->versions()->create(['organization_id' => $this->org, 'version' => 2, 'state' => 'DRAFT', 'conditions' => ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => ['lte' => 10000], 'currency' => ['eq' => 'USD']], 'effect' => 'ALLOW', 'reason_code' => 'LOWER_LIMIT']);
        $run = $this->replay($draft);
        $row = $run->results()->firstOrFail();
        $this->assertSame('ALLOW', $row->baseline_effect);
        $this->assertSame('DENY', $row->candidate_effect);
        $this->assertSame('DEFAULT_DENY', $row->candidate_outcome['reason_code']);
        $this->assertCount(1, $run->baseline_manifest);
    }

    #[DataProvider('boundaries')]
    public function test_replay_money_boundaries_and_precedence(int $amount, string $expected): void
    {
        $this->artisan('scope:demo-policies', ['--organization' => $this->org])->assertSuccessful();
        $this->api(['action_name' => 'refund.issue', 'amount_minor' => $amount, 'currency' => 'USD'])->assertOk();
        $run = $this->replay($this->rule());
        $this->assertSame($expected, $run->results()->first()->candidate_effect);
    }

    public static function boundaries(): array
    {
        return [[0, 'ALLOW'], [10000, 'ALLOW'], [10001, 'DENY'], [50000, 'DENY'], [50001, 'DENY'], [240000, 'DENY']];
    }

    public function test_replay_run_and_results_remain_frozen_after_draft_edit_publish_and_disable(): void
    {
        $draft = $this->demo();
        $run = $this->replay($draft);
        $before = $this->state(['replay_runs', 'replay_results']);
        $this->actingAs($this->owner)->put('/policies/'.$draft->policy_id, ['name' => 'Edited', 'effect' => 'ALLOW', 'reason_code' => 'EDITED', 'conditions' => ['action_name' => ['eq' => 'other.action']]])->assertRedirect();
        $this->post('/policies/'.$draft->policy_id.'/publish', ['draft_id' => $draft->id])->assertRedirect();
        $this->delete('/policies/'.$draft->policy_id)->assertRedirect();
        $this->assertSame($before, $this->state(['replay_runs', 'replay_results']));
        $this->assertSame('DENY', $run->fresh()->candidate_snapshot['effect']);
    }

    public function test_missing_published_disabled_foreign_or_malformed_drafts_cannot_be_replayed(): void
    {
        $this->actingAs($this->owner);
        $this->postJson('/replays', [])->assertUnprocessable();
        $this->postJson('/replays', ['draft_id' => (string) Str::uuid()])->assertNotFound();
        $published = $this->rule(published: true);
        $this->postJson('/replays', ['draft_id' => $published->id])->assertNotFound();
        $disabled = $this->rule();
        $disabled->policy->update(['status' => 'DISABLED']);
        $this->postJson('/replays', ['draft_id' => $disabled->id])->assertNotFound();
        $malformed = $this->rule();
        $malformed->update(['conditions' => ['action_name' => ['regex' => '.*']]]);
        // Separate direct service check avoids the intentional five-run HTTP rate limit.
        $this->expectException(ValidationException::class);
        $this->replay($malformed);
    }

    public function test_replay_empty_history_limits_and_rate_limit(): void
    {
        $draft = $this->rule();
        $this->actingAs($this->owner);
        $this->postJson('/replays', ['draft_id' => $draft->id, 'limit' => 501])->assertUnprocessable();
        $this->postJson('/replays', ['draft_id' => $draft->id, 'limit' => 0])->assertUnprocessable();
        $this->post('/replays', ['draft_id' => $draft->id])->assertRedirect();
        $this->assertSame(0, ReplayRun::first()->sample_count);
        $this->post('/replays', ['draft_id' => $draft->id])->assertRedirect();
        $this->post('/replays', ['draft_id' => $draft->id])->assertRedirect();
        $this->postJson('/replays', ['draft_id' => $draft->id])->assertStatus(429);
        $this->assertDatabaseCount('replay_runs', 3);
    }

    public function test_sample_defaults_to_100_caps_at_500_and_uses_bounded_selects(): void
    {
        $actions = [];
        $decisions = [];
        $timestamp = now();
        for ($i = 0; $i < 501; $i++) {
            $id = (string) Str::uuid();
            $actions[] = ['id' => $id, 'organization_id' => $this->org, 'agent_id' => $this->agent->id, 'idempotency_key' => 'fixture-'.$i, 'action_name' => 'unknown.action', 'normalized_request_json' => '{"action_name":"unknown.action"}', 'created_at' => $timestamp, 'updated_at' => $timestamp];
            $decisions[] = ['id' => (string) Str::uuid(), 'organization_id' => $this->org, 'agent_id' => $this->agent->id, 'action_request_id' => $id, 'decision' => 'DENY', 'reason_code' => 'DEFAULT_DENY', 'evaluated_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp];
        }
        foreach (array_chunk($actions, 100) as $chunk) {
            ActionRequest::insert($chunk);
        }
        foreach (array_chunk($decisions, 100) as $chunk) {
            PolicyDecision::insert($chunk);
        }
        $draft = $this->rule();
        $this->assertSame(100, $this->replay($draft)->sample_count);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $large = $this->replay($draft, ['limit' => 500]);
        $selects = count(array_filter(DB::getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'select')));
        DB::disableQueryLog();
        $this->assertSame(500, $large->sample_count);
        $this->assertCount(500, $large->sample_manifest);
        $this->assertLessThan(15, $selects, 'Replay sampling/evaluation should not issue per-result SELECT queries.');
    }

    public function test_replay_filters_agent_action_and_dates_are_scoped(): void
    {
        $draft = $this->demo();
        $run = $this->replay($draft, ['agent_id' => $this->agent->id, 'action_name' => 'unknown.action', 'from' => now()->toDateString(), 'to' => now()->toDateString()]);
        $this->assertSame(1, $run->sample_count);
        $this->assertSame(0, $run->summary['changed']);
        $empty = $this->replay($draft, ['from' => now()->addDay()->toDateString()]);
        $this->assertSame(0, $empty->sample_count);
        $this->actingAs($this->owner)->postJson('/replays', ['draft_id' => $draft->id, 'from' => '2026-10-20', 'to' => '2026-10-19'])->assertUnprocessable();
    }

    public function test_new_review_creates_one_approval_atomically_and_retries_do_not_duplicate(): void
    {
        $this->rule('REVIEW', ['action_name' => ['eq' => 'refund.issue']], true);
        $response = $this->api(key: 'review')->assertOk()->json();
        $this->api(key: 'review')->assertExactJson($response);
        $this->assertDatabaseCount('approval_requests', 1);
        $approval = ApprovalRequest::firstOrFail();
        $this->assertSame('PENDING', $approval->status);
        $this->assertSame($response['decision_id'], $approval->policy_decision_id);
        $this->assertSame(24 * 60 * 60, (int) $approval->created_at->diffInSeconds($approval->expires_at));
        $this->assertSame(SnapshotManifest::fingerprint(PolicySnapshots::frozen($this->org)), $approval->policy_fingerprint);
        $this->assertArrayNotHasKey('resource_label', $approval->action_summary);
        $this->assertSame(1, AuditEvent::where('event_type', 'approval.requested')->count());
        $this->api(['action_name' => 'unknown.action'])->assertJsonPath('decision', 'DENY');
        $this->rule('ALLOW', ['action_name' => ['eq' => 'customer.read']], true);
        $this->api(['action_name' => 'customer.read'])->assertJsonPath('decision', 'ALLOW');
        $this->assertDatabaseCount('approval_requests', 1);
    }

    #[DataProvider('rolesAndTargets')]
    public function test_owner_admin_can_resolve_once_without_network_or_history_writes(string $role, string $target): void
    {
        $approval = $this->pending();
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->update(['role' => $role]);
        $before = $this->state(['action_requests', 'policy_decisions', 'policy_versions', 'agent_api_keys']);
        Http::preventStrayRequests();
        $this->actingAs($this->owner)->postJson('/approvals/'.$approval->id.'/resolve', ['status' => $target])->assertOk()->assertJsonPath('approval.status', $target)->assertJsonPath('approval.execution_performed', false);
        $this->postJson('/approvals/'.$approval->id.'/resolve', ['status' => $target === 'APPROVED' ? 'REJECTED' : 'APPROVED'])->assertStatus(409);
        $this->assertSame($before, $this->state(['action_requests', 'policy_decisions', 'policy_versions', 'agent_api_keys']));
        $this->assertSame($this->owner->id, $approval->fresh()->resolved_by_user_id);
        $this->assertSame(1, AuditEvent::where('event_type', 'approval.'.strtolower($target))->count());
        Http::assertNothingSent();
    }

    public static function rolesAndTargets(): array
    {
        return [['OWNER', 'APPROVED'], ['OWNER', 'REJECTED'], ['ADMIN', 'APPROVED'], ['ADMIN', 'REJECTED']];
    }

    public function test_expiry_at_24_hours_blocks_resolution_and_gets_are_read_only(): void
    {
        $approval = $this->pending();
        $this->travel(24)->hours();
        $this->actingAs($this->owner)->get('/approvals/'.$approval->id)->assertInertia(fn (Assert $p) => $p->where('approval.status', 'EXPIRED')->where('approval.can_resolve', false));
        $this->assertSame('PENDING', $approval->fresh()->status);
        $this->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'APPROVED'])->assertStatus(409)->assertJsonPath('approval.status', 'EXPIRED');
        $this->assertSame('EXPIRED', $approval->fresh()->status);
        $this->assertSame(1, AuditEvent::where('event_type', 'approval.expired')->count());
    }

    #[DataProvider('invalidatingChanges')]
    public function test_published_membership_change_key_revocation_or_agent_disable_blocks_approval(string $change, string $reason): void
    {
        $approval = $this->pending();
        if ($change === 'publish') {
            $this->rule('DENY', published: true);
        } elseif ($change === 'disable') {
            Policy::forOrganization($this->org)->update(['status' => 'DISABLED']);
        } elseif ($change === 'enable') {
            $v = $this->rule('DENY', published: true);
            $v->policy->update(['status' => 'DISABLED']);
            $approval->update(['policy_manifest' => PolicySnapshots::frozen($this->org), 'policy_fingerprint' => SnapshotManifest::fingerprint(PolicySnapshots::frozen($this->org))]);
            $v->policy->update(['status' => 'ACTIVE']);
        } elseif ($change === 'key') {
            $approval->agentKey->update(['revoked_at' => now()]);
        } else {
            $this->agent->update(['status' => 'DISABLED']);
        }
        $this->actingAs($this->owner)->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'APPROVED'])->assertStatus(409)->assertJsonPath('approval.status', 'STALE')->assertJsonPath('approval.resolution_reason', $reason);
        $this->assertSame('REVIEW', $approval->decision->decision);
        $this->assertSame('STALE', $approval->fresh()->status);
    }

    public static function invalidatingChanges(): array
    {
        return [['publish', 'POLICY_CHANGED'], ['disable', 'POLICY_CHANGED'], ['enable', 'POLICY_CHANGED'], ['key', 'KEY_REVOKED'], ['agent', 'AGENT_DISABLED']];
    }

    public function test_unpublished_draft_edits_do_not_invalidate_pending_approval(): void
    {
        $approval = $this->pending();
        $draft = $this->rule();
        $draft->update(['effect' => 'ALLOW', 'reason_code' => 'EDITED_DRAFT']);
        $this->actingAs($this->owner)->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'APPROVED'])->assertOk()->assertJsonPath('approval.status', 'APPROVED');
    }

    public function test_agent_lookup_is_read_only_and_confined_to_originating_agent_and_key_auth(): void
    {
        $approval = $this->pending();
        $url = '/api/v1/agent/decisions/'.$approval->policy_decision_id.'/approval';
        $before = $this->state(['approval_requests', 'audit_events', 'agent_api_keys']);
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('status', 'PENDING')->assertJsonPath('execution_performed', false)->assertDontSee($this->token);
        $this->assertSame($before, $this->state(['approval_requests', 'audit_events', 'agent_api_keys']));
        $another = Agent::create(['organization_id' => $this->org, 'name' => 'Second', 'status' => 'ACTIVE']);
        $this->getJson($url, ['Authorization' => 'Bearer '.AgentKeys::issue($another)])->assertNotFound();
        $approval->agentKey->update(['revoked_at' => now()]);
        $this->getJson($url, $headers)->assertUnauthorized();
    }

    public function test_historical_build01_review_is_not_backfilled_and_requires_reevaluation(): void
    {
        $approval = $this->pending();
        $id = $approval->policy_decision_id;
        // Fixture simulates a decision recorded before the approval table existed.
        $oldIdempotencyKey = ActionRequest::findOrFail($approval->action_request_id)->idempotency_key;
        $approval->delete();
        $this->assertDatabaseCount('approval_requests', 0);
        $this->api(key: $oldIdempotencyKey)->assertOk()->assertJsonPath('decision_id', $id)->assertJsonPath('decision', 'REVIEW');
        $this->assertDatabaseCount('approval_requests', 0);
        $this->actingAs($this->owner)->get('/approvals?tab=historical')->assertInertia(fn (Assert $p) => $p->where('counts.historical', 1)->has('historical.data', 1));
        $this->getJson('/api/v1/agent/decisions/'.$id.'/approval', ['Authorization' => 'Bearer '.$this->token])->assertOk()->assertJsonPath('status', 'REEVALUATION_REQUIRED');
        $this->assertDatabaseCount('approval_requests', 0);
    }

    public function test_viewer_reads_all_new_pages_but_cannot_run_replay_or_resolve(): void
    {
        $draft = $this->demo();
        $run = $this->replay($draft);
        $approval = ApprovalRequest::firstOrFail();
        DB::table('organization_memberships')->where('user_id', $this->owner->id)->update(['role' => 'VIEWER']);
        $this->actingAs($this->owner);
        foreach (['/replays', '/replays/'.$run->id, '/replays/'.$run->id.'/results/'.$run->results()->first()->id, '/approvals', '/approvals/'.$approval->id, '/usage'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->postJson('/replays', ['draft_id' => $draft->id])->assertForbidden();
        $this->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'APPROVED'])->assertForbidden();
        $this->assertSame('PENDING', $approval->fresh()->status);
    }

    public function test_foreign_runs_results_drafts_samples_approvals_and_status_are_not_disclosed(): void
    {
        $draft = $this->demo();
        $run = $this->replay($draft);
        $result = $run->results()->first();
        $approval = ApprovalRequest::first();
        $outsider = User::factory()->create();
        $foreignOrg = OrganizationContext::membership($outsider)->organization_id;
        $foreignDraft = $this->rule(org: $foreignOrg);
        $foreignAgent = Agent::create(['organization_id' => $foreignOrg, 'name' => 'Foreign', 'status' => 'ACTIVE']);
        $foreignToken = AgentKeys::issue($foreignAgent);
        $this->actingAs($outsider);
        foreach (['/replays/'.$run->id, '/replays/'.$run->id.'/results/'.$result->id, '/approvals/'.$approval->id] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->get('/replays')->assertInertia(fn (Assert $p) => $p->has('runs.data', 0));
        $this->get('/approvals')->assertInertia(fn (Assert $p) => $p->has('approvals.data', 0)->where('counts.historical', 0));
        $this->postJson('/replays', ['draft_id' => $draft->id])->assertNotFound();
        $this->postJson('/replays', ['draft_id' => $foreignDraft->id, 'agent_id' => $this->agent->id])->assertNotFound();
        $this->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'APPROVED'])->assertNotFound();
        $this->getJson('/api/v1/agent/decisions/'.$approval->policy_decision_id.'/approval', ['Authorization' => 'Bearer '.$foreignToken])->assertNotFound();
        $foreignRun = app(ReplayService::class)->create($outsider, $foreignDraft->id, []);
        $this->assertSame(0, $foreignRun->sample_count);
        $this->get('/replays/'.$foreignRun->id.'/results/'.$result->id)->assertNotFound();
        $this->assertSame('PENDING', $approval->fresh()->status);
    }

    public function test_new_routes_reject_malformed_ids_and_tenant_overrides(): void
    {
        $draft = $this->rule();
        $this->actingAs($this->owner);
        foreach (['/replays/bad', '/replays/'.Str::uuid().'/results/bad', '/approvals/bad'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson('/replays', ['draft_id' => $draft->id, 'organization_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson('/approvals/'.Str::uuid().'/resolve', ['status' => 'APPROVED', 'organization_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->getJson('/api/v1/agent/decisions/bad/approval', ['Authorization' => 'Bearer '.$this->token])->assertNotFound();
    }

    public function test_usage_counts_are_real_scoped_and_exclude_retries(): void
    {
        $draft = $this->demo();
        $this->replay($draft);
        $this->actingAs($this->owner)->get('/usage')->assertInertia(fn (Assert $p) => $p->where('counts.requests', 3)->where('counts.approvals', 1)->where('counts.replays', 1)->where('effects.ALLOW', 1)->where('effects.REVIEW', 1)->where('effects.DENY', 1)->has('days', 30)->where('agents.0.decisions_count', 3));
        $other = User::factory()->create();
        $this->actingAs($other)->get('/usage')->assertInertia(fn (Assert $p) => $p->where('counts.requests', 0)->where('counts.approvals', 0)->where('counts.replays', 0)->has('agents', 0));
    }

    public function test_fingerprint_is_stable_for_key_order_but_changes_with_rule_content(): void
    {
        $this->assertSame(SnapshotManifest::fingerprint(['b' => 2, 'a' => ['y' => 1, 'x' => 2]]), SnapshotManifest::fingerprint(['a' => ['x' => 2, 'y' => 1], 'b' => 2]));
        $this->assertNotSame(SnapshotManifest::fingerprint(['lte' => 10000]), SnapshotManifest::fingerprint(['lte' => 10001]));
    }

    public function test_approval_creation_failure_rolls_back_entire_evaluation_transaction(): void
    {
        $this->rule('REVIEW', ['action_name' => ['eq' => 'refund.issue']], true);
        ApprovalRequest::creating(fn () => throw new \RuntimeException('Simulated approval persistence failure.'));
        $this->api()->assertStatus(500);
        foreach (['action_requests', 'policy_decisions', 'approval_requests', 'audit_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull($this->agent->keys()->first()->last_used_at);
    }

    public function test_approval_before_expiry_is_valid_and_terminal_state_cannot_be_reopened(): void
    {
        $approval = $this->pending();
        $this->travel(23)->hours();
        $this->travel(59)->minutes();
        $this->actingAs($this->owner)->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'APPROVED'])->assertOk();
        $this->travel(2)->hours();
        $this->get('/approvals/'.$approval->id)->assertInertia(fn (Assert $p) => $p->where('approval.status', 'APPROVED'));
        $this->postJson('/approvals/'.$approval->id.'/resolve', ['status' => 'REJECTED'])->assertStatus(409);
    }

    public function test_replay_to_date_without_start_and_agent_read_only_status_variants(): void
    {
        $draft = $this->demo();
        $this->actingAs($this->owner)->post('/replays', ['draft_id' => $draft->id, 'to' => now()->toDateString()])->assertRedirect();
        $this->assertSame(3, ReplayRun::first()->sample_count);
        $deny = PolicyDecision::where('decision', 'DENY')->firstOrFail();
        $this->getJson('/api/v1/agent/decisions/'.$deny->id.'/approval', ['Authorization' => 'Bearer '.$this->token])->assertOk()->assertJsonPath('status', 'NOT_REQUIRED');
        $review = ApprovalRequest::firstOrFail();
        $this->postJson('/approvals/'.$review->id.'/resolve', ['status' => 'APPROVED'])->assertOk();
        $this->getJson('/api/v1/agent/decisions/'.$review->policy_decision_id.'/approval', ['Authorization' => 'Bearer '.$this->token])->assertOk()->assertJsonPath('status', 'APPROVED')->assertJsonPath('execution_performed', false);
    }

    public function test_shared_organization_replay_limit_cannot_be_bypassed_by_another_admin(): void
    {
        $draft = $this->rule();
        $second = User::factory()->create();
        $third = User::factory()->create();
        foreach ([$second, $third] as $admin) {
            DB::table('organization_memberships')->where('user_id', $admin->id)->delete();
            DB::table('organization_memberships')->insert(['organization_id' => $this->org, 'user_id' => $admin->id, 'role' => 'ADMIN', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([$this->owner, $second] as $actor) {
            $this->actingAs($actor);
            for ($i = 0; $i < 5; $i++) {
                $this->post('/replays', ['draft_id' => $draft->id])->assertRedirect();
            }
        }
        $this->actingAs($third)->postJson('/replays', ['draft_id' => $draft->id])->assertStatus(429);
        $this->assertDatabaseCount('replay_runs', 10);
    }
}
