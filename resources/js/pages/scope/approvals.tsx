import { ApprovalRecord } from '@/components/approval';
import { ActionAmount, Paginated } from '@/components/replay';
import { DateTime, DecisionRecord, DecisionsTable, Empty, Pagination, Pill, ScopeLayout } from '@/components/scope';
import { Link } from '@inertiajs/react';

export default function Approvals({
    approvals,
    counts,
    tab,
    historical,
}: {
    approvals: Paginated<ApprovalRecord>;
    counts: Record<string, number>;
    tab: string;
    historical: Paginated<DecisionRecord> | null;
}) {
    const rows = tab === 'historical' ? historical! : approvals;
    return (
        <ScopeLayout title="Approvals" subtitle="Review agent requests with a policy-bound, time-limited decision trail.">
            <div className="scope-info scope-approval-disclosure">
                An approval records a human decision in ScopeTwin. It never executes a refund, transfers money, or calls an external tool.
            </div>
            <section className="scope-panel">
                <nav className="scope-tabs" aria-label="Approval filters">
                    {[
                        { id: 'pending', label: 'Pending' },
                        { id: 'resolved', label: 'Resolved' },
                        { id: 'historical', label: 'Historical reviews' },
                    ].map((t) => (
                        <Link
                            key={t.id}
                            className={tab === t.id ? 'active' : ''}
                            href={`/approvals?tab=${t.id}`}
                            aria-current={tab === t.id ? 'page' : undefined}
                        >
                            {t.label}
                            <span>{counts[t.id]}</span>
                        </Link>
                    ))}
                </nav>
                {tab === 'historical' ? (
                    <>
                        <div className="scope-pad scope-legacy-note">
                            <strong>Reevaluation required</strong>
                            <p>
                                These REVIEW decisions predate actionable approvals or lack their original policy manifest. They are read-only and
                                were not backfilled. Send a new API evaluation with a new idempotency key.
                            </p>
                        </div>
                        <DecisionsTable rows={historical!.data} />
                    </>
                ) : approvals.data.length ? (
                    <div className="scope-table-scroll">
                        <table className="scope-table">
                            <thead>
                                <tr>
                                    <th>Action / agent</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Policy reason</th>
                                    <th>{tab === 'pending' ? 'Expires' : 'Updated'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {approvals.data.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <Link className="scope-table-title" href={`/approvals/${a.id}`}>
                                                {a.action_summary.action_name}
                                            </Link>
                                            <small>{a.agent.name}</small>
                                        </td>
                                        <td className="scope-mono">
                                            <ActionAmount action={a.action_summary} />
                                        </td>
                                        <td>
                                            <Pill value={a.status} />
                                        </td>
                                        <td className="scope-reason">{a.decision.reason_code}</td>
                                        <td>
                                            <DateTime value={tab === 'pending' ? a.expires_at : (a.resolved_at ?? a.created_at)} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty title={tab === 'pending' ? 'No pending approvals' : 'No resolved approvals'}>
                        New REVIEW evaluations create a policy-bound approval with a 24-hour review window. Expired and stale requests appear under
                        Resolved.
                    </Empty>
                )}
                <Pagination current={rows.current_page} last={rows.last_page} prev={rows.prev_page_url} next={rows.next_page_url} />
            </section>
        </ScopeLayout>
    );
}
