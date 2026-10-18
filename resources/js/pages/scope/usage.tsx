import { Empty, Pill, ScopeLayout } from '@/components/scope';

export default function Usage({
    days,
    effects,
    agents,
    counts,
}: {
    days: { date: string; ALLOW: number; REVIEW: number; DENY: number }[];
    effects: Record<string, number>;
    agents: { id: string; name: string; decisions_count: number }[];
    counts: Record<string, number>;
}) {
    const max = Math.max(...days.map((d) => d.ALLOW + d.REVIEW + d.DENY), 1);
    return (
        <ScopeLayout title="Usage & Plans" subtitle="Actual workspace activity over the last 30 days. Pricing is a proposal, with no charges.">
            <div className="scope-stats">
                {[
                    { label: 'Evaluated requests', count: counts.requests, note: 'Persisted requests · excludes retries' },
                    { label: 'Replay runs', count: counts.replays, note: 'Actual comparisons created' },
                    { label: 'Approval requests', count: counts.approvals, note: 'New REVIEW records only' },
                    { label: 'Denied requests', count: effects.DENY ?? 0, note: 'Policy or default deny' },
                ].map((s) => (
                    <div className="scope-stat" key={s.label}>
                        <span>{s.label}</span>
                        <strong>{s.count}</strong>
                        <small>{s.note}</small>
                    </div>
                ))}
            </div>
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>Daily evaluations</h2>
                        <p>
                            ALLOW {effects.ALLOW ?? 0} · REVIEW {effects.REVIEW ?? 0} · DENY {effects.DENY ?? 0}
                        </p>
                    </div>
                    <span className="scope-period">Last 30 days · UTC</span>
                </div>
                <div
                    className="scope-usage-chart"
                    role="img"
                    aria-label={days.map((d) => `${d.date}: ${d.ALLOW} allowed, ${d.REVIEW} reviewed, ${d.DENY} denied`).join('; ')}
                >
                    {days.map((d) => (
                        <div key={d.date} title={`${d.date}: ${d.ALLOW + d.REVIEW + d.DENY} requests`}>
                            <div className="scope-usage-track">
                                {(['DENY', 'REVIEW', 'ALLOW'] as const).map((effect) => (
                                    <i key={effect} className={effect.toLowerCase()} style={{ height: `${(d[effect] / max) * 100}%` }} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
                <div className="scope-chart-labels">
                    <span>{days[0]?.date}</span>
                    <span>{days[days.length - 1]?.date}</span>
                </div>
            </section>
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>Usage by agent</h2>
                        <p>Top 50 agents by recorded evaluations in this window</p>
                    </div>
                </div>
                {agents.length ? (
                    <div className="scope-table-scroll">
                        <table className="scope-table">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Evaluations</th>
                                </tr>
                            </thead>
                            <tbody>
                                {agents.map((a) => (
                                    <tr key={a.id}>
                                        <td>{a.name}</td>
                                        <td>{a.decisions_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty title="No agent activity">Create an agent and send a real policy evaluation to start measuring usage.</Empty>
                )}
            </section>
            <section className="scope-panel scope-form-panel">
                <h2>Proposed pricing</h2>
                <p>Pricing preview — no charges. There is no checkout, active subscription, billing provider, or enforced paid entitlement.</p>
                <div className="scope-three-col scope-plan-grid">
                    {[
                        { name: 'Free sandbox', price: '$0', detail: 'Local evaluation and policy experimentation. Current workspace is a sandbox.' },
                        { name: 'Team', price: '$79/month', detail: 'Hypothesis: shared policy operations, replay and approvals for a small team.' },
                        { name: 'Business', price: '$299/month', detail: 'Hypothesis: higher-volume operations and additional governance controls.' },
                    ].map((plan) => (
                        <article className="scope-plan" key={plan.name}>
                            <Pill value="PROPOSED" />
                            <h3>{plan.name}</h3>
                            <strong>{plan.price}</strong>
                            <p>{plan.detail}</p>
                        </article>
                    ))}
                </div>
            </section>
        </ScopeLayout>
    );
}
