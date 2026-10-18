import { ActionSummary, RuleSnapshot } from '@/components/replay';
export interface ApprovalRecord {
    id: string;
    policy_decision_id: string;
    status: string;
    recorded_status: string;
    action_summary: ActionSummary;
    created_at: string;
    expires_at: string;
    resolved_at: string | null;
    resolution_reason: string | null;
    invalid_reason: string | null;
    policy_fingerprint: string;
    policy_manifest: RuleSnapshot[];
    can_resolve: boolean;
    agent: { id: string; name: string; status: string };
    decision: { reason_code: string };
    resolver: { name: string } | null;
}
export const invalidMessages: Record<string, string> = {
    TIME_EXPIRED: 'The 24-hour review window has expired. Request a fresh evaluation.',
    POLICY_CHANGED: 'The active published policy set changed. Request a fresh evaluation.',
    AGENT_DISABLED: 'This agent is disabled. Approval is blocked.',
    KEY_REVOKED: 'The key that originated this request was revoked. Request a fresh evaluation with a valid key.',
};
