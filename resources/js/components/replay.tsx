import { Pill, formatAmount } from '@/components/scope';

export interface Outcome {
    decision: string;
    reason_code: string;
    match: {
        source: string;
        policy_id?: string;
        policy_version_id: string | null;
        draft_version_id?: string | null;
        fingerprint?: string;
        version?: number;
    } | null;
}
export interface ReplayRunRecord {
    id: string;
    candidate_policy_name: string;
    candidate_policy_id: string;
    created_at: string;
    completed_at: string;
    sample_count: number;
    baseline_fingerprint: string;
    candidate_fingerprint: string;
    baseline_manifest: RuleSnapshot[];
    candidate_snapshot: RuleSnapshot;
    summary: { changed: number; newly_allowed: number; newly_reviewed: number; newly_denied: number; transitions: Record<string, number> };
    actor?: { name: string } | null;
}
export interface RuleSnapshot {
    id: string;
    policy_id: string;
    version: number;
    effect: string;
    reason_code: string;
    conditions: Record<string, Record<string, unknown>>;
    source?: string;
    fingerprint?: string;
}
export interface ReplayResultRecord {
    id: string;
    historical_action_request_id: string;
    historical_policy_decision_id: string;
    input_snapshot: ActionSummary;
    original_effect: string;
    baseline_effect: string;
    candidate_effect: string;
    changed: boolean;
    original_outcome: Outcome;
    baseline_outcome: Outcome;
    candidate_outcome: Outcome;
}
export interface ActionSummary {
    action_name: string;
    amount_minor?: number;
    currency?: string;
    resource_type?: string;
    destination_domain?: string;
}
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export function ActionAmount({ action }: { action: ActionSummary }) {
    return <>{action.amount_minor !== undefined && action.currency ? formatAmount(action.amount_minor, action.currency) : '—'}</>;
}
export function OutcomeCard({ title, detail, outcome }: { title: string; detail: string; outcome: Outcome }) {
    return (
        <section className="scope-panel scope-form-panel scope-outcome">
            <span className="scope-eyebrow">{title}</span>
            <p>{detail}</p>
            <div className="scope-outcome-effect">
                <Pill value={outcome.decision} />
            </div>
            <strong className="scope-mono">{outcome.reason_code}</strong>
            <p>
                {outcome.match
                    ? `${outcome.match.source === 'DRAFT' ? 'Unpublished draft' : 'Published snapshot'}${outcome.match.version ? ` · v${outcome.match.version}` : ''}`
                    : 'No matching rule · default deny'}
            </p>
            {outcome.match && <code>{outcome.match.policy_version_id ?? outcome.match.draft_version_id}</code>}
        </section>
    );
}
export function TransitionMatrix({ counts }: { counts: Record<string, number> }) {
    const effects = ['ALLOW', 'REVIEW', 'DENY'];
    return (
        <section className="scope-panel">
            <div className="scope-panel-heading">
                <div>
                    <h2>Baseline → Candidate</h2>
                    <p>Each cell counts requests. Off-diagonal cells are decision changes.</p>
                </div>
            </div>
            <div className="scope-table-scroll" tabIndex={0} role="region" aria-label="Decision transition matrix">
                <table className="scope-matrix">
                    <caption className="sr-only">Rows: current baseline. Columns: simulated candidate.</caption>
                    <thead>
                        <tr>
                            <th>Baseline / Candidate</th>
                            {effects.map((e) => (
                                <th key={e}>{e}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {effects.map((before) => (
                            <tr key={before}>
                                <th>{before}</th>
                                {effects.map((after) => (
                                    <td className={before !== after && counts[`${before}_${after}`] ? 'scope-matrix-change' : ''} key={after}>
                                        {counts[`${before}_${after}`] ?? 0}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
