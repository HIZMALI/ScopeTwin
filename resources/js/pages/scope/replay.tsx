import { ActionAmount, Paginated, ReplayResultRecord, ReplayRunRecord, TransitionMatrix } from '@/components/replay';
import { AdvisorState, ReplayAdvisor } from '@/components/replay-advisor';
import { DateTime, Empty, Pagination, Pill, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Replay({
    run,
    results,
    filters,
    advisor,
}: {
    run: ReplayRunRecord;
    results: Paginated<ReplayResultRecord>;
    filters: { action?: string; changed?: string; effect?: string };
    advisor: AdvisorState;
}) {
    const { canManage } = usePage<ScopeShared>().props;
    const [action, setAction] = useState(filters.action ?? '');
    const [changed, setChanged] = useState(filters.changed ?? '');
    const [effect, setEffect] = useState(filters.effect ?? '');
    return (
        <ScopeLayout
            title="Draft policy impact"
            subtitle={`${run.candidate_policy_name} · Frozen comparison of ${run.sample_count} historical requests`}
            action={
                <Button asChild variant="outline">
                    <Link href="/replays">Replay history</Link>
                </Button>
            }
        >
            <div className="scope-stats">
                {[
                    { label: 'Changed decisions', count: run.summary.changed, note: 'Candidate versus baseline' },
                    { label: 'Newly allowed', count: run.summary.newly_allowed, note: 'Changed to ALLOW' },
                    { label: 'Newly reviewed', count: run.summary.newly_reviewed, note: 'Changed to REVIEW' },
                    { label: 'Newly denied', count: run.summary.newly_denied, note: 'Changed to DENY' },
                ].map((s, i) => (
                    <div className="scope-stat" key={s.label}>
                        <span>
                            <i className={`stat-dot stat-${i}`} />
                            {s.label}
                        </span>
                        <strong>{s.count}</strong>
                        <small>{s.note}</small>
                    </div>
                ))}
            </div>
            {run.sample_count === 0 ? (
                <section className="scope-panel">
                    <Empty title="No historical requests matched">
                        No sample outcomes were generated.{' '}
                        <Link className="scope-text-link" href="/agents">
                            Send a real test API request
                        </Link>{' '}
                        or adjust the filters.
                    </Empty>
                </section>
            ) : (
                run.summary.changed === 0 && (
                    <div className="scope-info scope-no-changes">
                        <strong>No changes detected</strong>
                        <p>
                            The candidate produces the same decision effects as the frozen baseline for every sampled request. Reasons or matching
                            rules may still differ.
                        </p>
                    </div>
                )
            )}
            <div className="scope-two-col">
                <section className="scope-panel scope-form-panel">
                    <h2>Frozen policy context</h2>
                    <dl className="scope-details">
                        <dt>Baseline</dt>
                        <dd>{run.baseline_manifest.length} active published snapshots at run creation</dd>
                        <dt>Candidate</dt>
                        <dd>
                            Baseline with {run.candidate_policy_name} replaced or added as draft v{run.candidate_snapshot.version}
                        </dd>
                        <dt>Completed</dt>
                        <dd>
                            <DateTime value={run.completed_at} />
                        </dd>
                        <dt>Created by</dt>
                        <dd>{run.actor?.name ?? 'Former member'}</dd>
                    </dl>
                    <p>
                        The original recorded result may differ from the baseline because other policies changed before this replay. Only candidate
                        versus baseline measures this draft’s impact.
                    </p>
                    <details className="scope-disclosure">
                        <summary>View frozen policy definitions and fingerprints</summary>
                        <p className="scope-mono">
                            Baseline: {run.baseline_fingerprint}
                            <br />
                            Candidate: {run.candidate_fingerprint}
                        </p>
                        <pre>{JSON.stringify({ baseline: run.baseline_manifest, candidate: run.candidate_snapshot }, null, 2)}</pre>
                    </details>
                </section>
                <TransitionMatrix counts={run.summary.transitions} />
            </div>
            <ReplayAdvisor runId={run.id} advisor={advisor} canManage={canManage} />
            <section className="scope-panel">
                <form
                    className="scope-filters scope-wrap"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(`/replays/${run.id}`, {
                            ...(action ? { action } : {}),
                            ...(changed ? { changed } : {}),
                            ...(effect ? { effect } : {}),
                        });
                    }}
                >
                    <div>
                        <Label htmlFor="replay-action">Search action (exact)</Label>
                        <Input
                            id="replay-action"
                            value={action}
                            onChange={(e) => setAction(e.target.value)}
                            maxLength={128}
                            placeholder="All actions"
                        />
                    </div>
                    <div>
                        <Label htmlFor="replay-change">Impact</Label>
                        <select id="replay-change" value={changed} onChange={(e) => setChanged(e.target.value)}>
                            <option value="">All requests</option>
                            <option value="1">Changed decisions</option>
                            <option value="0">Unchanged decisions</option>
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="replay-effect">Candidate effect</Label>
                        <select id="replay-effect" value={effect} onChange={(e) => setEffect(e.target.value)}>
                            <option value="">All effects</option>
                            <option>ALLOW</option>
                            <option>REVIEW</option>
                            <option>DENY</option>
                        </select>
                    </div>
                    <Button type="submit" variant="outline">
                        Apply filters
                    </Button>
                </form>
                {results.data.length ? (
                    <div className="scope-table-scroll" tabIndex={0} role="region" aria-label="Three-way replay results table">
                        <table className="scope-table">
                            <thead>
                                <tr>
                                    <th>Request / amount</th>
                                    <th>Original recorded</th>
                                    <th>Baseline simulated</th>
                                    <th>Candidate simulated</th>
                                    <th>Impact</th>
                                </tr>
                            </thead>
                            <tbody>
                                {results.data.map((r) => (
                                    <tr key={r.id}>
                                        <td>
                                            <Link className="scope-table-title" href={`/replays/${run.id}/results/${r.id}`}>
                                                {r.input_snapshot.action_name}
                                            </Link>
                                            <small>
                                                <ActionAmount action={r.input_snapshot} />
                                            </small>
                                        </td>
                                        <td>
                                            <Pill value={r.original_effect} />
                                            <small>{r.original_outcome.reason_code}</small>
                                        </td>
                                        <td>
                                            <Pill value={r.baseline_effect} />
                                            <small>{r.baseline_outcome.reason_code}</small>
                                        </td>
                                        <td>
                                            <Pill value={r.candidate_effect} />
                                            <small>
                                                {r.candidate_outcome.reason_code}
                                                {r.candidate_outcome.match?.source === 'DRAFT' ? ' · draft' : ''}
                                            </small>
                                        </td>
                                        <td>{r.changed ? 'Changed' : 'Unchanged'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty title="No matching sample rows">Try a different action, effect or impact filter.</Empty>
                )}
                <Pagination current={results.current_page} last={results.last_page} prev={results.prev_page_url} next={results.next_page_url} />
            </section>
        </ScopeLayout>
    );
}
