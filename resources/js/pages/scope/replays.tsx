import { Paginated, ReplayRunRecord } from '@/components/replay';
import { DateTime, Empty, Pagination, Pill, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function Replays({
    runs,
    drafts,
    agents,
    historyCount,
}: {
    runs: Paginated<ReplayRunRecord>;
    drafts: { id: string; version: number; effect: string; policy: { name: string } }[];
    agents: { id: string; name: string }[];
    historyCount: number;
}) {
    const { canManage } = usePage<ScopeShared>().props;
    const form = useForm({ draft_id: drafts[0]?.id ?? '', limit: 100, agent_id: '', action_name: '', from: '', to: '' });
    return (
        <ScopeLayout title="Replay Lab" subtitle="Measure a draft against historical requests before publishing it.">
            <div className="scope-callout scope-replay-banner">
                <div>
                    <strong>Recorded history. Frozen baseline. Simulated candidate.</strong>
                    <p>Impact compares candidate with baseline. Neither replay nor approval executes an external action.</p>
                </div>
            </div>
            {canManage && (
                <section className="scope-panel scope-form-panel">
                    <h2>Run a policy comparison</h2>
                    <p>{historyCount} recorded requests available in this workspace. Latest 100 by default, maximum 500 per run.</p>
                    {drafts.length ? (
                        <form
                            className="scope-form"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/replays');
                            }}
                        >
                            <div className="scope-replay-inputs">
                                <div>
                                    <Label htmlFor="draft">Candidate draft</Label>
                                    <select id="draft" required value={form.data.draft_id} onChange={(e) => form.setData('draft_id', e.target.value)}>
                                        {drafts.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.policy.name} · v{d.version} · {d.effect}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label htmlFor="sample-limit">Sample size</Label>
                                    <Input
                                        id="sample-limit"
                                        type="number"
                                        required
                                        min={1}
                                        max={500}
                                        value={form.data.limit}
                                        onChange={(e) => form.setData('limit', Number(e.target.value))}
                                    />
                                </div>
                            </div>
                            <details className="scope-disclosure">
                                <summary>Optional sample filters</summary>
                                <div className="scope-replay-inputs">
                                    <div>
                                        <Label htmlFor="sample-agent">Agent</Label>
                                        <select
                                            id="sample-agent"
                                            value={form.data.agent_id}
                                            onChange={(e) => form.setData('agent_id', e.target.value)}
                                        >
                                            <option value="">All agents</option>
                                            {agents.map((a) => (
                                                <option key={a.id} value={a.id}>
                                                    {a.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="sample-action">Action name (exact)</Label>
                                        <Input
                                            id="sample-action"
                                            maxLength={128}
                                            placeholder="All actions"
                                            value={form.data.action_name}
                                            onChange={(e) => form.setData('action_name', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="sample-from">From date (UTC)</Label>
                                        <Input
                                            id="sample-from"
                                            type="date"
                                            value={form.data.from}
                                            onChange={(e) => form.setData('from', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="sample-to">Through date (UTC)</Label>
                                        <Input id="sample-to" type="date" value={form.data.to} onChange={(e) => form.setData('to', e.target.value)} />
                                    </div>
                                </div>
                            </details>
                            {Object.keys(form.errors).length > 0 && (
                                <p className="scope-error" role="alert">
                                    {Object.values(form.errors).join(' ')}
                                </p>
                            )}
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Comparing requests…' : 'Run replay'}
                            </Button>
                        </form>
                    ) : (
                        <Empty title="Save a draft to get started">
                            Only unpublished drafts of active policies can be replayed.{' '}
                            <Link className="scope-text-link" href="/policies">
                                Open Policies
                            </Link>
                        </Empty>
                    )}
                    {historyCount === 0 && (
                        <div className="scope-info">
                            No recorded requests yet.{' '}
                            <Link href="/agents" className="scope-text-link">
                                Create an agent and send a test API request
                            </Link>
                            . Replays never invent sample traffic.
                        </div>
                    )}
                </section>
            )}
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>Replay history</h2>
                        <p>Frozen results remain available after policies change.</p>
                    </div>
                </div>
                {runs.data.length ? (
                    <>
                        <div className="scope-table-scroll" tabIndex={0} role="region" aria-label="Replay history table">
                            <table className="scope-table">
                                <thead>
                                    <tr>
                                        <th>Candidate policy</th>
                                        <th>Requests</th>
                                        <th>Changed</th>
                                        <th>Created by</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {runs.data.map((run) => (
                                        <tr key={run.id}>
                                            <td>
                                                <Link className="scope-table-title" href={`/replays/${run.id}`}>
                                                    {run.candidate_policy_name}
                                                </Link>
                                                <small>
                                                    Draft v{run.candidate_snapshot.version} · {run.candidate_snapshot.effect}
                                                </small>
                                            </td>
                                            <td>{run.sample_count}</td>
                                            <td>
                                                <Pill value={run.summary.changed ? 'CHANGED' : 'UNCHANGED'} />
                                                <small>{run.summary.changed} decision changes</small>
                                            </td>
                                            <td>{run.actor?.name ?? 'Former member'}</td>
                                            <td>
                                                <DateTime value={run.completed_at} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination current={runs.current_page} last={runs.last_page} prev={runs.prev_page_url} next={runs.next_page_url} />
                    </>
                ) : (
                    <Empty title="No comparisons yet">Run a draft comparison to see its actual impact on your recorded traffic.</Empty>
                )}
            </section>
        </ScopeLayout>
    );
}
