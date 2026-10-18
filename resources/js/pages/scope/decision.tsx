import { DateTime, DecisionRecord, Pill, ScopeLayout } from '@/components/scope';
import { Link } from '@inertiajs/react';

export default function Decision({ decision: d }: { decision: DecisionRecord }) {
    return (
        <ScopeLayout title="Decision detail" subtitle="An immutable record of the policy result at evaluation time.">
            <div className="scope-two-col">
                <section className="scope-panel scope-form-panel">
                    <div className="scope-detail-heading">
                        <h2>{d.action_request.action_name}</h2>
                        <Pill value={d.decision} />
                    </div>
                    <dl className="scope-details">
                        <dt>Decision ID</dt>
                        <dd className="scope-mono">{d.id}</dd>
                        <dt>Reason code</dt>
                        <dd className="scope-mono">{d.reason_code}</dd>
                        <dt>Agent</dt>
                        <dd>
                            <Link className="scope-text-link" href={`/agents/${d.agent.id}`}>
                                {d.agent.name}
                            </Link>
                        </dd>
                        <dt>Evaluated</dt>
                        <dd>
                            <DateTime value={d.evaluated_at} />
                        </dd>
                        <dt>Policy snapshot</dt>
                        <dd>
                            {d.policy_version ? `Version ${d.policy_version.version} / ${d.policy_version.id}` : 'No matching rule. Default deny.'}
                        </dd>
                    </dl>
                    {d.decision === 'REVIEW' && (
                        <div className="scope-info">Review is required. The approval queue is planned for Build 02; no action has been executed.</div>
                    )}
                </section>
                <section className="scope-panel scope-form-panel">
                    <h2>Normalized request</h2>
                    <p>Validated fields only. Free-text resource labels are discarded.</p>
                    <pre>{JSON.stringify(d.action_request.normalized_request_json, null, 2)}</pre>
                    <h3 className="mt-6">Matched conditions</h3>
                    <pre>{JSON.stringify(d.policy_version?.conditions ?? {}, null, 2)}</pre>
                    <p>History retains the original policy snapshot when a newer version is published.</p>
                </section>
            </div>
        </ScopeLayout>
    );
}
