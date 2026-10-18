<?php

// Run only against the disposable PostgreSQL test database. Does not use HTTP credentials.
use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\PolicyDecision;
use App\Services\AgentKeys;
use App\Services\EvaluateAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'pgsql' || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Use PostgreSQL with a disposable *_test database.');
}
if (($argv[1] ?? '') === 'worker') {
    $key = AgentApiKey::findOrFail($argv[2]);
    echo "ready\n";
    flush();
    try {
        $result = app(EvaluateAction::class)->handle($key, $argv[3], ['action_name' => 'refund.issue', 'amount_minor' => (int) $argv[4], 'currency' => 'USD']);
        echo json_encode(['status' => 200, ...$result])."\n";
    } catch (ConflictHttpException $e) {
        echo json_encode(['status' => 409])."\n";
    }
    exit(0);
}
$org = Organization::create(['name' => 'Concurrency verification', 'slug' => (string) Str::uuid()]);
try {
    $agent = Agent::create(['organization_id' => $org->id, 'name' => 'Concurrency agent', 'status' => 'ACTIVE']);
    AgentKeys::issue($agent); // Discard the raw token immediately.
    $key = $agent->keys()->firstOrFail();
    $run = function (string $idempotency, array $amounts) use ($agent, $key): array {
        DB::beginTransaction();
        Agent::whereKey($agent->id)->lockForUpdate()->firstOrFail();
        $processes = [];
        foreach ($amounts as $amount) {
            $p = new Process([PHP_BINARY, __FILE__, 'worker', $key->id, $idempotency, (string) $amount], base_path());
            $p->setTimeout(30);
            $p->start();
            $processes[] = $p;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'ready')));
            if ($ready === count($processes)) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        DB::commit();
        if ($ready !== count($processes)) {
            throw new RuntimeException('Workers did not reach barrier.');
        }

        return array_map(function ($p) {
            $p->wait();
            if (! $p->isSuccessful()) {
                throw new RuntimeException('Worker failed: '.$p->getErrorOutput());
            }
            $lines = explode("\n", trim($p->getOutput()));

            return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    };
    $same = $run('parallel-identical', [12000, 12000, 12000, 12000]);
    if (count(array_unique(array_column($same, 'decision_id'))) !== 1 || array_column($same, 'status') !== [200, 200, 200, 200]) {
        throw new RuntimeException('Identical requests did not return the same result.');
    }
    $conflict = $run('parallel-conflicting', [100, 200]);
    $statuses = array_column($conflict, 'status');
    sort($statuses);
    if ($statuses !== [200, 409] || ActionRequest::forOrganization($org->id)->count() !== 2 || PolicyDecision::forOrganization($org->id)->count() !== 2 || AuditEvent::forOrganization($org->id)->count() !== 2) {
        throw new RuntimeException('Concurrent conflict created unexpected records.');
    }
    echo "PASS: 4 identical concurrent requests => one decision; 2 conflicting requests => 200 + 409; exactly 2 requests, decisions, and audits.\n";
} finally {
    if (DB::transactionLevel()) {
        DB::rollBack();
    }
    foreach ([AuditEvent::class, PolicyDecision::class, ActionRequest::class, AgentApiKey::class, Agent::class] as $model) {
        $model::forOrganization($org->id)->delete();
    }
    $org->delete();
}
