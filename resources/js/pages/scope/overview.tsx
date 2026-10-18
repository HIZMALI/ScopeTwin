import { DecisionRecord, DecisionsTable, ScopeLayout } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, Bot, Check, ShieldCheck } from 'lucide-react';

export default function Overview({
    counts,
    recent,
    traffic,
}: {
    counts: Record<string, number>;
    recent: DecisionRecord[];
    traffic: { date: string; count: number }[];
}) {
    const max = Math.max(...traffic.map((t) => t.count), 1);
    return (
        <ScopeLayout
            title="Overview"
            subtitle="A clear view of what your agents are allowed to do."
            action={
                <Button asChild>
                    <Link href="/agents">
                        Manage agents
                        <ArrowUpRight size={16} />
                    </Link>
                </Button>
            }
        >
            <div className="scope-stats">
                {[
                    { label: 'Total evaluations', key: 'total', note: 'All recorded API requests' },
                    { label: 'Allowed', key: 'allow', note: 'Matched an allow policy' },
                    { label: 'Requires review', key: 'review', note: 'Flagged for human review' },
                    { label: 'Denied', key: 'deny', note: 'Blocked by policy or default' },
                ].map((stat, i) => (
                    <div className="scope-stat" key={stat.key}>
                        <span>
                            <i className={`stat-dot stat-${i}`} />
                            {stat.label}
                        </span>
                        <strong>{counts[stat.key].toLocaleString()}</strong>
                        <small>{stat.note}</small>
                    </div>
                ))}
            </div>
            <div className="scope-overview-grid">
                <section className="scope-panel">
                    <div className="scope-panel-heading">
                        <div>
                            <h2>Evaluation activity</h2>
                            <p>Actual request volume over the last 7 days</p>
                        </div>
                        <span className="scope-period">Last 7 days</span>
                    </div>
                    <div className="scope-chart" role="img" aria-label={traffic.map((t) => `${t.date}: ${t.count} evaluations`).join(', ')}>
                        {traffic.map((t, i) => (
                            <div key={i}>
                                <span>{t.count}</span>
                                <div className="scope-chart-track">
                                    <i style={{ height: `${(t.count / max) * 100}%`, minHeight: t.count ? 4 : 0 }} />
                                </div>
                                <small>{t.date}</small>
                            </div>
                        ))}
                    </div>
                </section>
                <section className="scope-panel scope-posture">
                    <div className="scope-panel-heading">
                        <div>
                            <h2>Workspace controls</h2>
                            <p>Deterministic by design</p>
                        </div>
                        <ShieldCheck size={20} />
                    </div>
                    <Link href="/agents">
                        <span>
                            <Bot size={17} />
                            Active agents
                        </span>
                        <strong>
                            {counts.agents}
                            <ArrowUpRight size={14} />
                        </strong>
                    </Link>
                    <Link href="/policies">
                        <span>
                            <ShieldCheck size={17} />
                            Published policies
                        </span>
                        <strong>
                            {counts.policies}
                            <ArrowUpRight size={14} />
                        </strong>
                    </Link>
                    <div className="scope-default-deny">
                        <span>
                            <Check size={15} />
                            Default deny is enforced
                        </span>
                        <p>Actions without a matching rule are denied. Deny rules always take precedence.</p>
                    </div>
                </section>
            </div>
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>Recent decisions</h2>
                        <p>A traceable result for every evaluated action</p>
                    </div>
                    <Link className="scope-text-link" href="/decisions">
                        View all
                        <ArrowRight size={15} />
                    </Link>
                </div>
                <DecisionsTable rows={recent} />
            </section>
            <div className="scope-callout">
                <ShieldCheck size={19} />
                <div>
                    <strong>Start with a narrow policy. Expand with confidence.</strong>
                    <p>Only published versions affect live evaluations. Drafts stay separate from your running controls.</p>
                </div>
                <Link href="/policies" className="scope-text-link">
                    View policies
                    <ArrowRight size={16} />
                </Link>
            </div>
        </ScopeLayout>
    );
}
