<?php

namespace App\Services;

use App\Domain\SnapshotManifest;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\ApprovalRequest;
use App\Models\PolicyDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ApprovalService
{
    public static function create(PolicyDecision $decision, AgentApiKey $key, array $action, array $manifest): void
    {
        $approval = ApprovalRequest::create(['organization_id' => $decision->organization_id, 'agent_id' => $decision->agent_id, 'agent_api_key_id' => $key->id,
            'action_request_id' => $decision->action_request_id, 'policy_decision_id' => $decision->id, 'status' => 'PENDING', 'action_summary' => $action,
            'policy_manifest' => $manifest, 'policy_fingerprint' => SnapshotManifest::fingerprint($manifest), 'expires_at' => now()->addHours(24)]);
        Audit::record($decision->organization_id, 'approval.requested', $approval->id, 'AGENT', $decision->agent_id, ['decision_id' => $decision->id]);
    }

    public static function invalidReason(ApprovalRequest $approval, string $currentFingerprint): ?string
    {
        if ($approval->expires_at->lessThanOrEqualTo(now())) {
            return 'TIME_EXPIRED';
        }
        if ($approval->agent->status !== 'ACTIVE') {
            return 'AGENT_DISABLED';
        }
        if ($approval->agentKey->revoked_at !== null) {
            return 'KEY_REVOKED';
        }
        if (! hash_equals($approval->policy_fingerprint, $currentFingerprint)) {
            return 'POLICY_CHANGED';
        }

        return null;
    }

    public static function present(ApprovalRequest $approval, string $currentFingerprint): array
    {
        $reason = $approval->status === 'PENDING' ? self::invalidReason($approval, $currentFingerprint) : null;
        $effective = $reason ? ($reason === 'TIME_EXPIRED' ? 'EXPIRED' : 'STALE') : $approval->status;

        return [...$approval->toArray(), 'recorded_status' => $approval->status, 'status' => $effective, 'invalid_reason' => $reason,
            'can_resolve' => $effective === 'PENDING', 'execution_performed' => false];
    }

    public static function pendingQuery(string $org, string $fingerprint): Builder
    {
        return ApprovalRequest::forOrganization($org)->where('status', 'PENDING')->where('expires_at', '>', now())->where('policy_fingerprint', $fingerprint)
            ->whereHas('agent', fn ($q) => $q->where('organization_id', $org)->where('status', 'ACTIVE'))
            ->whereHas('agentKey', fn ($q) => $q->where('organization_id', $org)->whereNull('revoked_at'));
    }

    public function resolve(User $actor, string $id, string $target): array
    {
        $org = OrganizationContext::writableId($actor);
        abort_unless(in_array($target, ['APPROVED', 'REJECTED'], true), 422);

        return DB::transaction(function () use ($actor, $org, $id, $target) {
            $reference = ApprovalRequest::forOrganization($org)->findOrFail($id);
            // Same order as evaluation/revocation: agent, key, org-policy state, approval.
            Agent::forOrganization($org)->whereKey($reference->agent_id)->lockForUpdate()->firstOrFail();
            AgentApiKey::forOrganization($org)->whereKey($reference->agent_api_key_id)->lockForUpdate()->firstOrFail();
            PolicySnapshots::lock($org);
            $approval = ApprovalRequest::forOrganization($org)->with(['agent', 'agentKey', 'decision', 'resolver'])->lockForUpdate()->findOrFail($id);
            $fingerprint = SnapshotManifest::fingerprint(PolicySnapshots::frozen($org));
            if ($approval->status !== 'PENDING') {
                return ['conflict' => true, 'message' => 'This request is already resolved. Request a fresh evaluation if needed.', 'approval' => self::present($approval, $fingerprint)];
            }
            $invalid = self::invalidReason($approval, $fingerprint);
            $status = $invalid ? ($invalid === 'TIME_EXPIRED' ? 'EXPIRED' : 'STALE') : $target;
            $approval->update(['status' => $status, 'resolution_reason' => $invalid ?? $target.'_BY_OPERATOR', 'resolved_by_user_id' => $actor->id, 'resolved_at' => now()]);
            Audit::record($org, 'approval.'.strtolower($status), $id, 'USER', (string) $actor->id, ['decision_id' => $approval->policy_decision_id, 'status' => $status, 'reason' => $approval->resolution_reason]);
            $approval->load('resolver');

            return ['conflict' => $invalid !== null, 'message' => $invalid ? 'Approval unavailable: '.$invalid.'. Request a fresh evaluation.' : ($status === 'APPROVED' ? 'Approved in ScopeTwin. No external action was executed.' : 'Rejected in ScopeTwin. No external action was executed.'),
                'approval' => self::present($approval, $fingerprint)];
        }, 3);
    }
}
