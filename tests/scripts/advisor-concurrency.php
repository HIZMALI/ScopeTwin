<?php

use App\Models\Organization;
use App\Models\Policy;
use App\Models\ReplayRun;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ReplayAdvisor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'pgsql' || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Disposable PostgreSQL *_test database required.');
}
config(['backboard.enabled' => true, 'backboard.api_key' => Str::random(48), 'backboard.llm_provider' => 'mock-provider', 'backboard.model_name' => 'mock-json-model']);
Http::preventStrayRequests();
if (($argv[1] ?? '') === 'worker') {
    Http::fake(function ($request) use ($argv) {
        if ($request->method() === 'DELETE') {
            return Http::response('', 204);
        }
        file_put_contents($argv[4], "POST\n", FILE_APPEND | LOCK_EX);
        usleep(300000);

        return Http::response(['status' => 'COMPLETED', 'thread_id' => (string) Str::uuid(), 'content' => json_encode([
            'explanation' => 'This is a simulated historical comparison. Human review can inspect the frozen differences.', 'questions' => []])]);
    });
    echo "ready\n";
    flush();
    $result = app(ReplayAdvisor::class)->advise(User::findOrFail($argv[2]), $argv[3]);
    echo json_encode(['status' => $result['status']])."\n";
    exit(0);
}
$owner = User::factory()->create();
$org = OrganizationContext::membership($owner)->organization_id;
$actors = [$owner];
$counter = storage_path('build035-concurrency-'.Str::uuid().'.txt');
try {
    $policy = Policy::create(['organization_id' => $org, 'name' => 'Synthetic concurrency fixture', 'status' => 'ACTIVE']);
    $run = ReplayRun::create(['organization_id' => $org, 'created_by_user_id' => $owner->id, 'candidate_policy_id' => $policy->id,
        'candidate_policy_name' => 'Synthetic concurrency fixture', 'candidate_source' => 'DRAFT', 'baseline_manifest' => [], 'baseline_fingerprint' => hash('sha256', 'baseline'),
        'candidate_snapshot' => [], 'candidate_fingerprint' => hash('sha256', 'candidate'), 'sample_manifest' => [], 'filters' => [], 'sample_count' => 0,
        'summary' => ['changed' => 0, 'newly_denied' => 0, 'newly_reviewed' => 0, 'transitions' => []], 'status' => 'COMPLETED', 'completed_at' => now()]);
    $race = function (array $pairs) use ($org, $counter): array {
        DB::beginTransaction();
        Organization::whereKey($org)->lockForUpdate()->firstOrFail();
        $processes = [];
        foreach ($pairs as [$user, $replay]) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $user, $replay, $counter], base_path());
            $process->setTimeout(30)->start();
            $processes[] = $process;
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
            throw new RuntimeException('Barrier timeout.');
        }

        return array_map(function ($p) {
            $p->wait();
            if (! $p->isSuccessful()) {
                throw new RuntimeException('Advisor worker failed; details withheld.');
            }
            $lines = explode("\n", trim($p->getOutput()));

            return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['status'];
        }, $processes);
    };
    $outcomes = $race(array_fill(0, 6, [$owner->id, $run->id]));
    if (count(file($counter)) !== 1 || array_diff($outcomes, ['completed', 'in_progress'])) {
        throw new RuntimeException('Duplicate generation race failed.');
    }
    echo "PASS: six identical concurrent requests => one mocked provider generation.\n";
    // Reset only this fixture's advisor counters/cache, never any user workspace.
    DB::table('advisor_explanations')->where('organization_id', $org)->delete();
    DB::table('advisor_quotas')->where('organization_id', $org)->delete();
    file_put_contents($counter, '');
    $pairs = [];
    for ($i = 0; $i < 6; $i++) {
        $actor = User::factory()->create();
        $actors[] = $actor;
        $ownOrg = OrganizationContext::membership($actor)->organization_id;
        DB::table('organization_memberships')->where('user_id', $actor->id)->update(['organization_id' => $org, 'role' => 'ADMIN']);
        Organization::whereKey($ownOrg)->delete();
        $copy = $run->replicate();
        $copy->candidate_fingerprint = hash('sha256', 'candidate'.$i);
        $copy->save();
        $pairs[] = [$actor->id, $copy->id];
    }
    $outcomes = $race($pairs);
    if (count(file($counter)) !== 3 || count(array_filter($outcomes, fn ($v) => $v === 'completed')) !== 3
        || count(array_filter($outcomes, fn ($v) => $v === 'quota_exhausted')) !== 3) {
        throw new RuntimeException('Organization quota race failed.');
    }
    echo "PASS: six admins / distinct frozen contents => three generations, three quota rejections.\n";
    DB::table('advisor_explanations')->where('organization_id', $org)->delete();
    DB::table('advisor_quotas')->where('organization_id', $org)->delete();
    file_put_contents($counter, '');
    $outcomes = $race(array_map(fn ($pair) => [$owner->id, $pair[1]], $pairs));
    if (count(file($counter)) !== 1 || count(array_filter($outcomes, fn ($v) => $v === 'quota_exhausted')) !== 5) {
        throw new RuntimeException('User quota race failed.');
    }
    echo "PASS: one user / six distinct concurrent requests => one generation, five minute-quota rejections. No network used.\n";
} finally {
    if (DB::transactionLevel()) {
        DB::rollBack();
    }
    DB::table('advisor_explanations')->where('organization_id', $org)->delete();
    DB::table('advisor_quotas')->where('organization_id', $org)->delete();
    ReplayRun::forOrganization($org)->delete();
    Policy::forOrganization($org)->delete();
    foreach ($actors as $actor) {
        $actor->delete();
    }
    Organization::whereKey($org)->delete();
    if (file_exists($counter)) {
        unlink($counter);
    }
}
