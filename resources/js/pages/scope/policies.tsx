import { Empty, Pill, PolicyRecord, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Plus } from 'lucide-react';

export default function Policies({ policies }: { policies: PolicyRecord[] }) {
    const { canManage } = usePage<ScopeShared>().props;
    return (
        <ScopeLayout
            title="Policies"
            subtitle="Explicit rules for agent actions. Every unmatched action is denied."
            action={
                canManage && (
                    <Button asChild>
                        <Link href="/policies/create">
                            <Plus size={16} />
                            Create policy
                        </Link>
                    </Button>
                )
            }
        >
            <div className="scope-rule-order">
                <strong>Decision precedence</strong>
                <Pill value="DENY" />
                <span>then</span>
                <Pill value="REVIEW" />
                <span>then</span>
                <Pill value="ALLOW" />
                <span className="scope-rule-note">All conditions in a rule must match.</span>
            </div>
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>Policy library</h2>
                        <p>{policies.length} rules · Drafts never affect live traffic</p>
                    </div>
                </div>
                {!policies.length ? (
                    <Empty title="Define your first boundary">Create and publish a typed policy to allow or flag specific agent actions.</Empty>
                ) : (
                    <div className="scope-table-scroll">
                        <table className="scope-table">
                            <thead>
                                <tr>
                                    <th>Policy / action</th>
                                    <th>Effect</th>
                                    <th>Live version</th>
                                    <th>Status</th>
                                    <th>
                                        <span className="sr-only">Open policy</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {policies.map((p) => {
                                    const latest = p.versions[0];
                                    const live = p.versions.find((v) => v.id === p.published_version_id);
                                    return (
                                        <tr key={p.id}>
                                            <td>
                                                <Link className="scope-table-title" href={`/policies/${p.id}`}>
                                                    {p.name}
                                                </Link>
                                                <small className="scope-mono">{JSON.stringify(latest.conditions.action_name)}</small>
                                            </td>
                                            <td>
                                                <Pill value={(live ?? latest).effect} />
                                            </td>
                                            <td>
                                                {live ? `v${live.version}` : 'Unpublished'}
                                                {latest.state === 'DRAFT' && <small>v{latest.version} draft available</small>}
                                            </td>
                                            <td>
                                                <Pill value={p.status} />
                                            </td>
                                            <td>
                                                <Link href={`/policies/${p.id}`} aria-label={`Open ${p.name}`}>
                                                    <ArrowUpRight size={17} />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </ScopeLayout>
    );
}
