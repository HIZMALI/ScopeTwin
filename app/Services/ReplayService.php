<?php

namespace App\Services;

use App\Domain\ActionSchema;
use App\Domain\ReplayComparison;
use App\Domain\SnapshotManifest;
use App\Models\Policy;
use App\Models\PolicyDecision;
use App\Models\PolicyVersion;
use App\Models\ReplayRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReplayService
{
    public function __construct(private ReplayComparison $comparison) {}

    public function create(User $actor, string $draftId, array $filters): ReplayRun
    {
        $org = OrganizationContext::writableId($actor);

        return DB::transaction(function () use ($actor, $org, $draftId, $filters) {
            // All policy mutations take the exclusive counterpart of this organization lock.
            // This freezes baseline, draft and the bounded selection in one transaction.
            PolicySnapshots::lock($org);
            $source = PolicyVersion::forOrganization($org)->where('state', 'DRAFT')->findOrFail($draftId);
            $policy = Policy::forOrganization($org)->where('status', 'ACTIVE')->findOrFail($source->policy_id);
            $draft = ReplayComparison::draft($source->toArray());
            $baseline = PolicySnapshots::frozen($org);
            $limit = (int) ($filters['limit'] ?? 100);
            abort_unless($limit >= 1 && $limit <= 500, 422, 'Replay limit must be between 1 and 500.');
            $query = PolicyDecision::forOrganization($org)->with('actionRequest');
            $query->whereHas('actionRequest', function ($q) use ($filters, $org) {
                $q->where('organization_id', $org);
                if (! empty($filters['agent_id'])) {
                    $q->where('agent_id', $filters['agent_id']);
                }
                if (! empty($filters['action_name'])) {
                    $q->where('action_name', $filters['action_name']);
                }
                if (! empty($filters['from'])) {
                    $q->where('created_at', '>=', $filters['from'].' 00:00:00');
                }
                if (! empty($filters['to'])) {
                    $q->where('created_at', '<=', $filters['to'].' 23:59:59');
                }
            });
            $records = $query->orderByDesc('evaluated_at')->orderByDesc('id')->limit($limit)->get();
            $summary = ['changed' => 0, 'newly_allowed' => 0, 'newly_reviewed' => 0, 'newly_denied' => 0, 'transitions' => []];
            $rows = [];
            $manifest = [];
            foreach ($records as $record) {
                $action = ActionSchema::normalize($record->actionRequest->normalized_request_json);
                $pair = $this->comparison->compare($action, $baseline, $draft);
                $before = $pair['baseline']['decision'];
                $after = $pair['candidate']['decision'];
                $changed = $before !== $after;
                $summary['changed'] += (int) $changed;
                if ($changed) {
                    $summary[match ($after) {
                        'ALLOW' => 'newly_allowed', 'REVIEW' => 'newly_reviewed', 'DENY' => 'newly_denied'
                    }]++;
                }
                $transition = $before.'_'.$after;
                $summary['transitions'][$transition] = ($summary['transitions'][$transition] ?? 0) + 1;
                $manifest[] = ['action_request_id' => $record->action_request_id, 'decision_id' => $record->id, 'input_fingerprint' => SnapshotManifest::fingerprint($action)];
                $rows[] = ['organization_id' => $org, 'historical_action_request_id' => $record->action_request_id, 'historical_policy_decision_id' => $record->id,
                    'input_snapshot' => $action, 'original_effect' => $record->decision, 'baseline_effect' => $before, 'candidate_effect' => $after, 'changed' => $changed,
                    'original_outcome' => ['decision' => $record->decision, 'reason_code' => $record->reason_code, 'match' => $record->policy_version_id ? ['source' => 'PUBLISHED', 'policy_version_id' => $record->policy_version_id] : null],
                    'baseline_outcome' => $pair['baseline'], 'candidate_outcome' => $pair['candidate']];
            }
            $run = ReplayRun::create(['organization_id' => $org, 'created_by_user_id' => $actor->id, 'candidate_policy_id' => $source->policy_id, 'candidate_source' => 'DRAFT',
                'candidate_policy_name' => $policy->name, 'baseline_manifest' => $baseline, 'baseline_fingerprint' => SnapshotManifest::fingerprint($baseline), 'candidate_snapshot' => SnapshotManifest::bounded($draft),
                'candidate_fingerprint' => $draft['fingerprint'], 'sample_manifest' => $manifest, 'filters' => $filters, 'sample_count' => count($rows),
                'summary' => $summary, 'status' => 'COMPLETED', 'completed_at' => now()]);
            // Fixed bounded batch, no per-row evaluator queries and no live decision writes.
            $run->results()->createMany($rows);
            Audit::record($org, 'replay.completed', $run->id, 'USER', (string) $actor->id, ['sample_count' => count($rows), 'changed' => $summary['changed']]);

            return $run;
        }, 3);
    }
}
