<?php

use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\Policy;
use App\Models\PolicyDecision;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Services\AgentKeys;
use App\Services\ApprovalService;
use App\Services\EvaluateAction;
use App\Services\OrganizationContext;
use App\Services\PolicySnapshots;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'pgsql' || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Disposable PostgreSQL *_test database required.');
}
if (($argv[1] ?? '') === 'worker') {
    echo "ready\n";
    flush();
    if ($argv[2] === 'evaluate') {
        $result = app(EvaluateAction::class)->handle(AgentApiKey::findOrFail($argv[3]), 'parallel-review', ['action_name' => 'refund.issue', 'amount_minor' => 240000, 'currency' => 'USD']);
        echo json_encode(['status' => 200, ...$result])."\n";
    } else {
        $result = app(ApprovalService::class)->resolve(User::findOrFail($argv[3]), $argv[4], $argv[5]);
        echo json_encode(['status' => $result['conflict'] ? 409 : 200, 'approval_status' => $result['approval']['status']])."\n";
    }
    exit(0);
}
$user = User::factory()->create();
$org = OrganizationContext::membership($user)->organization_id;
try {
    $agent = Agent::create(['organization_id' => $org, 'name' => 'Concurrency review fixture', 'status' => 'ACTIVE']);
    AgentKeys::issue($agent);
    $key = $agent->keys()->firstOrFail();
    $policy = Policy::create(['organization_id' => $org, 'name' => 'Concurrent review rule', 'status' => 'ACTIVE']);
    $version = $policy->versions()->create(['organization_id' => $org, 'version' => 1, 'state' => 'PUBLISHED', 'conditions' => ['action_name' => ['eq' => 'refund.issue']], 'effect' => 'REVIEW', 'reason_code' => 'CONCURRENT_REVIEW', 'published_at' => now()]);
    $policy->update(['published_version_id' => $version->id]);
    $barrier = function (array $commands, string $lock, ?Closure $mutation = null) use ($agent, $org): array {
        DB::beginTransaction();
        if ($lock === 'agent') {
            Agent::whereKey($agent->id)->lockForUpdate()->firstOrFail();
        } else {
            PolicySnapshots::lock($org, true);
        }
        $processes = [];
        foreach ($commands as $args) {
            $p = new Process([PHP_BINARY, __FILE__, 'worker', ...$args], base_path());
            $p->setTimeout(30);
            $p->start();
            $processes[] = $p;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'ready')));
            if ($ready === count($processes)) {
                break;
            } usleep(20000);
        } while (microtime(true) < $deadline);
        if ($mutation) {
            $mutation();
        }
        DB::commit();
        if ($ready !== count($processes)) {
            throw new RuntimeException('Barrier timeout.');
        }

        return array_map(function ($p) {
            $p->wait();
            if (! $p->isSuccessful()) {
                throw new RuntimeException('Worker failure: '.$p->getErrorOutput());
            } $lines = explode("\n", trim($p->getOutput()));

            return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    };
    $requests = $barrier(array_fill(0, 4, ['evaluate', $key->id]), 'agent');
    if (count(array_unique(array_column($requests, 'decision_id'))) !== 1 || ApprovalRequest::forOrganization($org)->count() !== 1) {
        throw new RuntimeException('Concurrent REVIEW idempotency failed.');
    }
    $approval = ApprovalRequest::forOrganization($org)->firstOrFail();
    $outcomes = $barrier([['resolve', (string) $user->id, $approval->id, 'APPROVED'], ['resolve', (string) $user->id, $approval->id, 'REJECTED']], 'agent');
    $statuses = array_column($outcomes, 'status');
    sort($statuses);
    if ($statuses !== [200, 409] || AuditEvent::forOrganization($org)->whereIn('event_type', ['approval.approved', 'approval.rejected'])->count() !== 1 || $approval->decision->decision !== 'REVIEW') {
        throw new RuntimeException('Approve/reject atomic terminal transition failed.');
    }
    echo "PASS: 4 concurrent REVIEW retries => one approval; simultaneous approve/reject => 200 + 409 and one terminal audit; original REVIEW unchanged.\n";
    $response = app(EvaluateAction::class)->handle($key, 'policy-race', ['action_name' => 'refund.issue', 'amount_minor' => 240000, 'currency' => 'USD']);
    $pending = ApprovalRequest::where('policy_decision_id', $response['decision_id'])->firstOrFail();
    $race = $barrier([['resolve', (string) $user->id, $pending->id, 'APPROVED']], 'org', fn () => $policy->update(['status' => 'DISABLED']));
    if ($race[0]['status'] !== 409 || $race[0]['approval_status'] !== 'STALE') {
        throw new RuntimeException('Policy change race accepted a stale approval.');
    }
    echo "PASS: approval blocked behind concurrent policy mutation => STALE / 409 after the new manifest commits.\n";
} finally {
    if (DB::transactionLevel()) {
        DB::rollBack();
    }
    foreach ([AuditEvent::class, ApprovalRequest::class, PolicyDecision::class, ActionRequest::class, AgentApiKey::class, Agent::class] as $model) {
        $model::forOrganization($org)->delete();
    }
    Policy::forOrganization($org)->update(['published_version_id' => null]);
    PolicyVersion::forOrganization($org)->delete();
    Policy::forOrganization($org)->delete();
    $user->delete();
    Organization::whereKey($org)->delete();
}
