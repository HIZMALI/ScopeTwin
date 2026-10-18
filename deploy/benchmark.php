<?php

use App\Domain\PolicyEvaluator;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ReplayService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (($argv[1] ?? '') !== '--confirm-disposable' || config('database.connections.pgsql.database') !== 'scopetwin_build03_rehearsal') {
    throw new RuntimeException('Benchmark is confined to the explicitly disposable rehearsal database.');
}
$start = microtime(true);
$owner = User::where('email', 'scale-fixture@example.test')->first();
if (! $owner) {
    DB::transaction(function () use (&$owner) {
        $owner = User::create(['name' => 'Synthetic scale fixture', 'email' => 'scale-fixture@example.test', 'password' => Str::random(80)]);
        $org = OrganizationContext::membership($owner)->organization_id;
        $foreign = Organization::create(['name' => 'Synthetic scale background', 'slug' => (string) Str::uuid()]);
        foreach ([$org => 1000, $foreign->id => 9000] as $workspace => $count) {
            $agent = Agent::create(['organization_id' => $workspace, 'name' => 'Synthetic scale agent', 'status' => 'ACTIVE']);
            $requests = $decisions = [];
            for ($i = 0; $i < $count; $i++) {
                $id = (string) Str::uuid();
                $time = now()->subSeconds($i)->toDateTimeString();
                $action = ['action_name' => 'scale.synthetic'];
                $result = app(PolicyEvaluator::class)->evaluate($action, []);
                $requests[] = ['id' => $id, 'organization_id' => $workspace, 'agent_id' => $agent->id, 'idempotency_key' => 'synthetic-scale-'.$i, 'action_name' => $action['action_name'], 'normalized_request_json' => json_encode($action), 'created_at' => $time, 'updated_at' => $time];
                $decisions[] = ['id' => (string) Str::uuid(), 'organization_id' => $workspace, 'agent_id' => $agent->id, 'action_request_id' => $id, ...$result, 'evaluated_at' => $time, 'created_at' => $time, 'updated_at' => $time];
                if (count($requests) === 250) {
                    DB::table('action_requests')->insert($requests);
                    DB::table('policy_decisions')->insert($decisions);
                    $requests = $decisions = [];
                }
            }
        }
    });
}
$org = OrganizationContext::membership($owner)->organization_id;
$plan = DB::select('EXPLAIN (ANALYZE, BUFFERS) SELECT id, decision FROM policy_decisions WHERE organization_id = ? ORDER BY evaluated_at DESC LIMIT 100', [$org]);
$policy = Policy::firstOrCreate(['organization_id' => $org, 'name' => 'Synthetic scale candidate'], ['status' => 'ACTIVE']);
$draft = PolicyVersion::firstOrCreate(['organization_id' => $org, 'policy_id' => $policy->id, 'version' => 1], ['state' => 'DRAFT', 'effect' => 'DENY', 'reason_code' => 'SYNTHETIC_SCALE', 'conditions' => ['action_name' => ['eq' => 'scale.synthetic']]]);
$selects = 0;
DB::listen(function ($q) use (&$selects) {
    $selects += (int) str_starts_with(strtolower($q->sql), 'select');
});
$replayStart = microtime(true);
$run = app(ReplayService::class)->create($owner, $draft->id, ['limit' => 500]);
$replayMs = round((microtime(true) - $replayStart) * 1000, 2);
if ($run->sample_count !== 500 || $run->summary['changed'] !== 0) {
    throw new RuntimeException('Scale replay correctness failed.');
}
echo json_encode(['fixture_decisions' => 10000, 'target_workspace_decisions' => 1000, 'background_decisions' => 9000, 'replay_sample' => 500, 'changed' => 0, 'replay_selects' => $selects, 'replay_ms' => $replayMs, 'total_ms' => round((microtime(true) - $start) * 1000, 2), 'query_plan' => array_column($plan, 'QUERY PLAN'), 'timestamp' => gmdate(DATE_ATOM)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
