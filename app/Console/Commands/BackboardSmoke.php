<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\ReplayRun;
use App\Models\User;
use App\Services\ReplayAdvisor;
use App\Support\AdvisorDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class BackboardSmoke extends Command
{
    protected $signature = 'scope:backboard-smoke {--live-backboard-smoke} {--confirm-charge-credits}';

    protected $description = 'Explicitly charge-confirmed, aggregate-only smoke in a dedicated synthetic demo database';

    public function handle(ReplayAdvisor $advisor): int
    {
        $database = config('database.connections.'.config('database.default').'.database');
        if (! $this->option('live-backboard-smoke') || ! $this->option('confirm-charge-credits') || $advisor->state() !== 'ready') {
            $this->line('LIVE_NOT_TESTED');

            return self::FAILURE;
        }
        // Never select an arbitrary customer replay. The dedicated fixture is required.
        if (! preg_match('/^scopetwin_[a-z0-9_]*(demo|rehearsal)$/D', $database)) {
            $this->line('FAIL: dedicated synthetic demo database required.');

            return self::FAILURE;
        }
        try {
            $org = Organization::whereKey(config('demo.organization_id'))->where('slug', config('demo.slug'))->firstOrFail();
            $run = ReplayRun::forOrganization($org->id)->firstOrFail();
            $expected = ['simulated' => true, 'sample_count' => 3, 'transitions' => ['ALLOW_ALLOW' => 0, 'ALLOW_REVIEW' => 0, 'ALLOW_DENY' => 1,
                'REVIEW_ALLOW' => 0, 'REVIEW_REVIEW' => 0, 'REVIEW_DENY' => 1, 'DENY_ALLOW' => 0, 'DENY_REVIEW' => 0, 'DENY_DENY' => 1],
                'changed' => 2, 'newly_denied' => 2, 'newly_reviewed' => 0, 'unchanged' => 1];
            if ($advisor->summary($run) !== $expected) {
                throw new \RuntimeException('Unexpected fixture.');
            }
            $before = $this->domainFingerprint($org->id);
            $result = $advisor->advise(User::findOrFail($run->created_by_user_id), $run->id);
            $unchanged = hash_equals($before, $this->domainFingerprint($org->id));
            if ($result['cached']) {
                $this->line('LIVE_NOT_TESTED: cached result; no new call.');

                return self::FAILURE;
            }
            $diagnostics = AdvisorDiagnostics::safe($result['diagnostics'] ?? []);
            $this->line(json_encode(['diagnostics' => $diagnostics, 'deterministic_unchanged' => $unchanged], JSON_THROW_ON_ERROR));
            $success = $result['status'] === 'completed' && ($result['cleanup_confirmed'] ?? false) && $unchanged
                && $diagnostics['generation_attempted'] && $diagnostics['validation_reason'] === 'accepted';
            $this->line(($success ? 'SUCCESS' : 'FAIL').': cleanup '.(($result['cleanup_confirmed'] ?? false) ? 'confirmed' : 'unconfirmed'));

            return $success ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->line('FAIL');

            return self::FAILURE;
        }
    }

    private function domainFingerprint(string $organization): string
    {
        $hashes = [];
        foreach (['agents', 'agent_api_keys', 'policies', 'policy_versions', 'action_requests', 'policy_decisions',
            'audit_events', 'approval_requests', 'replay_runs', 'replay_results'] as $table) {
            $hashes[$table] = hash('sha256', DB::table($table)->where('organization_id', $organization)->orderBy('id')->get()->toJson());
        }

        return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
    }
}
