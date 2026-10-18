<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Services\AgentKeys;
use App\Services\ApprovalService;
use App\Services\EvaluateAction;
use App\Services\ReplayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SetupJudgeDemo extends Command
{
    protected $signature = 'scope:judge-demo {--confirm-disposable : Confirm a dedicated disposable demo database}';

    protected $description = 'Opt-in idempotent synthetic judge artifacts; refuses user databases';

    public function handle(): int
    {
        $database = config('database.connections.'.config('database.default').'.database');
        if (! config('demo.enabled') || ! config('demo.allow_setup') || ! $this->option('confirm-disposable') ||
            (! app()->runningUnitTests() && ! preg_match('/^scopetwin_[a-z0-9_]*(demo|rehearsal)$/D', $database))) {
            $this->error('Refused: enable demo/setup and confirm a dedicated scopetwin_*demo or *_rehearsal database.');

            return self::FAILURE;
        }

        return DB::transaction(function () {
            $existing = Organization::whereKey(config('demo.organization_id'))->first();
            if ($existing) {
                if ($existing->slug !== config('demo.slug') || ! DB::table('replay_runs')->where('organization_id', $existing->id)->exists()) {
                    $this->error('Reserved demo identity is inconsistent; refusing to alter it.');

                    return self::FAILURE;
                }
                $this->info('Existing synthetic judge artifacts preserved; no changes made.');

                return self::SUCCESS;
            }
            if (User::exists() || Organization::exists()) {
                $this->error('First setup requires an empty dedicated database; existing workspaces are never seeded.');

                return self::FAILURE;
            }
            $org = Organization::create(['id' => config('demo.organization_id'), 'name' => 'Synthetic judge workspace', 'slug' => config('demo.slug')]);
            $owner = User::withoutEvents(fn () => User::create(['name' => 'Synthetic demo operator', 'email' => 'judge-fixture@example.test', 'password' => Str::random(80)]));
            DB::table('organization_memberships')->insert(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'created_at' => now(), 'updated_at' => now()]);
            $agent = Agent::create(['organization_id' => $org->id, 'name' => 'Synthetic Support Agent', 'status' => 'ACTIVE']);
            AgentKeys::issue($agent); // Random credential discarded immediately; never printed.
            $key = AgentApiKey::where('agent_id', $agent->id)->firstOrFail();
            foreach ([['Standard refunds', 'ALLOW', 'lte', 50000, 'REFUND_WITHIN_LIMIT'], ['Large refund review', 'REVIEW', 'gt', 50000, 'REFUND_REQUIRES_APPROVAL']] as [$name, $effect, $op, $amount, $reason]) {
                $policy = Policy::create(['organization_id' => $org->id, 'name' => $name, 'status' => 'ACTIVE']);
                $version = PolicyVersion::create(['organization_id' => $org->id, 'policy_id' => $policy->id, 'version' => 1, 'state' => 'PUBLISHED', 'effect' => $effect, 'reason_code' => $reason,
                    'conditions' => ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => [$op => $amount], 'currency' => ['eq' => 'USD']], 'published_at' => now()]);
                $policy->update(['published_version_id' => $version->id]);
            }
            foreach ([12000, 240000, null] as $i => $amount) {
                app(EvaluateAction::class)->handle($key, 'synthetic-judge-v1-'.$i, $amount === null ? ['action_name' => 'unknown.action'] : ['action_name' => 'refund.issue', 'amount_minor' => $amount, 'currency' => 'USD']);
            }
            $policy = Policy::create(['organization_id' => $org->id, 'name' => 'Proposed stricter refunds', 'status' => 'ACTIVE']);
            $draft = PolicyVersion::create(['organization_id' => $org->id, 'policy_id' => $policy->id, 'version' => 1, 'state' => 'DRAFT', 'effect' => 'DENY', 'reason_code' => 'PROPOSED_REFUND_LIMIT',
                'conditions' => ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => ['gt' => 10000], 'currency' => ['eq' => 'USD']]]);
            app(ReplayService::class)->create($owner, $draft->id, ['limit' => 3]);
            app(ApprovalService::class)->resolve($owner, ApprovalRequest::where('organization_id', $org->id)->firstOrFail()->id, 'APPROVED');
            $key->update(['revoked_at' => now()]);
            $this->info('Persisted synthetic demo: 3 decisions, 2 changed / 2 newly denied, documentary approval. No external actions.');

            return self::SUCCESS;
        }, 3);
    }
}
