<?php

namespace App\Services;

use App\Domain\PolicyEvaluator;
use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\PolicyDecision;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class EvaluateAction
{
    public function __construct(private PolicyEvaluator $evaluator) {}

    public function handle(AgentApiKey $key, string $idempotencyKey, array $action): array
    {
        return DB::transaction(function () use ($key, $idempotencyKey, $action) {
            // Serialize operations for one agent. Duplicate keys cannot race past this row lock.
            $agent = Agent::forOrganization($key->organization_id)->whereKey($key->agent_id)->lockForUpdate()->firstOrFail();
            $freshKey = AgentApiKey::forOrganization($agent->organization_id)->whereKey($key->id)->lockForUpdate()->firstOrFail();
            abort_unless($agent->status === 'ACTIVE' && $freshKey->revoked_at === null, 401);
            $existing = ActionRequest::forOrganization($agent->organization_id)->where('agent_id', $agent->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->normalized_request_json != $action) {
                    throw new ConflictHttpException('Idempotency key was already used with different input.');
                }

                return $this->response(PolicyDecision::where('action_request_id', $existing->id)->firstOrFail());
            }
            PolicySnapshots::lock($agent->organization_id);
            $manifest = PolicySnapshots::frozen($agent->organization_id);
            $result = $this->evaluator->evaluate($action, $manifest);
            $record = ActionRequest::create([
                'organization_id' => $agent->organization_id, 'agent_id' => $agent->id, 'idempotency_key' => $idempotencyKey,
                'action_name' => $action['action_name'], 'amount_minor' => $action['amount_minor'] ?? null,
                'currency' => $action['currency'] ?? null, 'destination_domain' => $action['destination_domain'] ?? null,
                'normalized_request_json' => $action,
            ]);
            $decision = PolicyDecision::create([
                ...$result, 'organization_id' => $agent->organization_id, 'agent_id' => $agent->id,
                'action_request_id' => $record->id, 'evaluated_at' => now(),
            ]);
            $freshKey->update(['last_used_at' => now()]);
            if ($decision->decision === 'REVIEW') {
                ApprovalService::create($decision, $freshKey, $action, $manifest);
            }
            Audit::record($agent->organization_id, 'action.evaluated', $decision->id, 'AGENT', $agent->id,
                ['decision' => $result['decision'], 'reason_code' => $result['reason_code']]);

            return $this->response($decision);
        }, 3);
    }

    private function response(PolicyDecision $decision): array
    {
        return ['decision_id' => $decision->id, 'decision' => $decision->decision, 'reason_code' => $decision->reason_code,
            'matched_policy_version_id' => $decision->policy_version_id, 'approval_required' => $decision->decision === 'REVIEW'];
    }
}
