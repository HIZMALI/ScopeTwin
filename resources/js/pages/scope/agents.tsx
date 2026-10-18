import OneTimeKey from '@/components/one-time-key';
import { AgentRecord, DateTime, Empty, issueKey, Pill, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function Agents({ agents }: { agents: AgentRecord[] }) {
    const { canManage } = usePage<ScopeShared>().props;
    const [name, setName] = useState('');
    const [token, setToken] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError('');
        try {
            const result = await issueKey('/agents', { name });
            setToken(result.token);
            setName('');
            router.reload({ only: ['agents'] });
        } catch (err) {
            setError((err as Error).message);
        } finally {
            setBusy(false);
        }
    }
    return (
        <ScopeLayout title="Agents" subtitle="Connect your agents with scoped, revocable API keys.">
            {token && <OneTimeKey token={token} dismiss={() => setToken('')} />}
            {canManage && (
                <section className="scope-panel scope-form-panel">
                    <h2>Add an agent</h2>
                    <p>Each key is bound to this workspace and a single agent.</p>
                    <form onSubmit={submit} className="scope-inline-form">
                        <div>
                            <Label htmlFor="agent-name">Agent name</Label>
                            <Input
                                id="agent-name"
                                placeholder="e.g. Support Agent"
                                value={name}
                                maxLength={100}
                                required
                                onChange={(e) => setName(e.target.value)}
                            />
                        </div>
                        <Button disabled={busy || !!token} type="submit">
                            <Plus size={16} />
                            {busy ? 'Creating…' : 'Create agent'}
                        </Button>
                    </form>
                    {error && (
                        <p className="scope-error" role="alert">
                            {error}
                        </p>
                    )}
                </section>
            )}
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>Connected agents</h2>
                        <p>{agents.length} agents in this workspace</p>
                    </div>
                </div>
                {!agents.length ? (
                    <Empty title="No agents connected">Add your first agent to receive an API key and start evaluating requests.</Empty>
                ) : (
                    <div className="scope-table-scroll">
                        <table className="scope-table">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Status</th>
                                    <th>Active keys</th>
                                    <th>Created</th>
                                    <th>
                                        <span className="sr-only">Details</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {agents.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <Link className="scope-table-title" href={`/agents/${a.id}`}>
                                                {a.name}
                                            </Link>
                                            <small className="scope-mono">{a.id.slice(0, 8)}</small>
                                        </td>
                                        <td>
                                            <Pill value={a.status} />
                                        </td>
                                        <td>{a.active_keys_count}</td>
                                        <td>
                                            <DateTime value={a.created_at} />
                                        </td>
                                        <td>
                                            <Link aria-label={`Manage ${a.name}`} href={`/agents/${a.id}`}>
                                                <ArrowUpRight size={17} />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </ScopeLayout>
    );
}
