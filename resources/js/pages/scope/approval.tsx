import { ApprovalRecord, invalidMessages } from '@/components/approval';
import { ActionAmount } from '@/components/replay';
import { DateTime, Pill, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Approval({ approval: a }: { approval: ApprovalRecord }) {
    const { canManage } = usePage<ScopeShared>().props;
    const [target, setTarget] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [error, setError] = useState(false);
    async function resolve() {
        setBusy(true);
        setMessage('');
        try {
            const response = await fetch(`/approvals/${a.id}/resolve`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ status: target }),
            });
            const result = await response.json();
            setMessage(result.message ?? 'Unable to resolve approval.');
            setError(!response.ok);
            setTarget('');
            router.reload({ only: ['approval', 'pendingApprovals'] });
        } catch {
            setTarget('');
            setMessage('The request could not be confirmed. Refresh this record before retrying.');
            setError(true);
        } finally {
            setBusy(false);
        }
    }
    return (
        <ScopeLayout
            title="Approval detail"
            subtitle="A human decision record, with no external execution capability."
            action={
                <Button asChild variant="outline">
                    <Link href="/approvals">Approval queue</Link>
                </Button>
            }
        >
            {message && (
                <div role={error ? 'alert' : 'status'} className={`scope-info scope-feedback ${error ? 'scope-error' : ''}`}>
                    {message}
                </div>
            )}
            {a.status === 'APPROVED' && !message && (
                <div className="scope-info scope-feedback">Approved in ScopeTwin. No external action was executed.</div>
            )}
            {a.status === 'REJECTED' && !message && (
                <div className="scope-info scope-feedback">Rejected in ScopeTwin. The original REVIEW decision is preserved.</div>
            )}
            {(a.invalid_reason || a.status === 'STALE' || a.status === 'EXPIRED') && (
                <div className="scope-info scope-feedback">
                    {invalidMessages[a.invalid_reason ?? a.resolution_reason ?? ''] ??
                        'This request is no longer actionable. Request a fresh evaluation.'}
                </div>
            )}
            <div className="scope-two-col">
                <section className="scope-panel scope-form-panel">
                    <div className="scope-detail-heading">
                        <h2>{a.action_summary.action_name}</h2>
                        <Pill value={a.status} />
                    </div>
                    <dl className="scope-details">
                        <dt>Agent</dt>
                        <dd>
                            <Link className="scope-text-link" href={`/agents/${a.agent.id}`}>
                                {a.agent.name}
                            </Link>
                        </dd>
                        <dt>Amount</dt>
                        <dd>
                            <ActionAmount action={a.action_summary} />
                        </dd>
                        <dt>Original reason</dt>
                        <dd className="scope-mono">{a.decision.reason_code}</dd>
                        <dt>Created</dt>
                        <dd>
                            <DateTime value={a.created_at} />
                        </dd>
                        <dt>Expires</dt>
                        <dd>
                            <DateTime value={a.expires_at} /> (24 hours)
                        </dd>
                        <dt>Resolved</dt>
                        <dd>{a.resolved_at ? <DateTime value={a.resolved_at} /> : 'Not recorded'}</dd>
                        <dt>Reviewer</dt>
                        <dd>{a.resolver?.name ?? 'Not assigned'}</dd>
                    </dl>
                    <Link className="scope-text-link mt-5" href={`/decisions/${a.policy_decision_id}`}>
                        Open immutable original REVIEW decision
                    </Link>
                    {canManage && a.can_resolve && (
                        <div className="scope-approval-actions">
                            <Button disabled={busy} onClick={() => setTarget('APPROVED')}>
                                Approve
                            </Button>
                            <Button variant="outline" disabled={busy} onClick={() => setTarget('REJECTED')}>
                                Reject
                            </Button>
                        </div>
                    )}
                    {!canManage && a.can_resolve && <p>Only an OWNER or ADMIN can resolve this request.</p>}
                    <p>
                        Published policy changes, originating key revocation, agent deactivation or expiry block approval. Editing an unpublished
                        draft alone does not invalidate it.
                    </p>
                </section>
                <section className="scope-panel scope-form-panel">
                    <h2>Safe action summary</h2>
                    <pre>{JSON.stringify(a.action_summary, null, 2)}</pre>
                    <h3>Policy context at evaluation</h3>
                    <p>{a.policy_manifest.length} published snapshots · Fingerprint</p>
                    <code className="scope-fingerprint">{a.policy_fingerprint}</code>
                    <details className="scope-disclosure">
                        <summary>View frozen policy manifest</summary>
                        <pre>{JSON.stringify(a.policy_manifest, null, 2)}</pre>
                    </details>
                    <p>No tool execution token or permission is issued by this approval.</p>
                </section>
            </div>
            <Dialog open={!!target} onOpenChange={(open) => !busy && !open && setTarget('')}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{target === 'APPROVED' ? 'Confirm approval' : 'Confirm rejection'}</DialogTitle>
                        <DialogDescription>
                            {target === 'APPROVED'
                                ? 'Record your approval in ScopeTwin. No refund, transfer, or external action will be executed.'
                                : 'Record your rejection. The original REVIEW decision remains unchanged.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" disabled={busy} onClick={() => setTarget('')}>
                            Cancel
                        </Button>
                        <Button disabled={busy} onClick={resolve}>
                            {busy ? 'Saving…' : target === 'APPROVED' ? 'Confirm approval' : 'Confirm rejection'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ScopeLayout>
    );
}
