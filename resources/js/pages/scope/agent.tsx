import OneTimeKey from '@/components/one-time-key';
import { AgentRecord, DateTime, issueKey, Pill, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';

export default function Agent({ agent }: { agent: AgentRecord }) {
    const { canManage } = usePage<ScopeShared>().props;
    const form = useForm({ name: agent.name, status: agent.status });
    const [token, setToken] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    return (
        <ScopeLayout title={agent.name} subtitle="Manage this agent’s access and send a real policy evaluation.">
            {token && <OneTimeKey token={token} dismiss={() => setToken('')} />}
            <div className="scope-two-col">
                <section className="scope-panel scope-form-panel">
                    <h2>Agent details</h2>
                    <form
                        className="scope-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.patch(`/agents/${agent.id}`);
                        }}
                    >
                        <div>
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                disabled={!canManage}
                                required
                                maxLength={100}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            <p className="scope-error">{form.errors.name}</p>
                        </div>
                        <div>
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                value={form.data.status}
                                disabled={!canManage}
                                onChange={(e) => form.setData('status', e.target.value)}
                            >
                                <option>ACTIVE</option>
                                <option>DISABLED</option>
                            </select>
                            <p>Disabling an agent revokes all its keys. Historical decisions are preserved.</p>
                        </div>
                        {canManage && (
                            <Button type="submit" disabled={form.processing}>
                                Save changes
                            </Button>
                        )}
                    </form>
                </section>
                <section className="scope-panel scope-form-panel">
                    <h2>Evaluate an action</h2>
                    <p>Decision-only endpoint. A refund is never executed.</p>
                    <div className="scope-endpoint">
                        <span>POST</span>
                        <code>/api/v1/agent/actions/evaluate</code>
                    </div>
                    <p>Send Authorization: Bearer &lt;your key&gt; and a unique Idempotency-Key header with each new request.</p>
                    <pre>
                        {JSON.stringify({ action_name: 'refund.issue', amount_minor: 12000, currency: 'USD', resource_type: 'refund' }, null, 2)}
                    </pre>
                    <p>Amounts use integer minor units: 12000 USD = $120.00. A repeated key and payload returns the original decision.</p>
                </section>
            </div>
            <section className="scope-panel">
                <div className="scope-panel-heading">
                    <div>
                        <h2>API keys</h2>
                        <p>Only prefixes are stored for display. Revocation takes effect on the next request.</p>
                    </div>
                    {canManage && (
                        <Button
                            size="sm"
                            disabled={busy || !!token || agent.status !== 'ACTIVE'}
                            onClick={async () => {
                                setBusy(true);
                                setError('');
                                try {
                                    setToken((await issueKey(`/agents/${agent.id}/keys`)).token);
                                    router.reload({ only: ['agent'] });
                                } catch (err) {
                                    setError((err as Error).message);
                                } finally {
                                    setBusy(false);
                                }
                            }}
                        >
                            <KeyRound size={15} />
                            Issue key
                        </Button>
                    )}
                </div>
                {error && (
                    <p role="alert" className="scope-error scope-pad">
                        {error}
                    </p>
                )}
                <div className="scope-table-scroll" tabIndex={0} role="region" aria-label="Agent keys table">
                    <table className="scope-table">
                        <thead>
                            <tr>
                                <th>Prefix</th>
                                <th>Status</th>
                                <th>Last evaluated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {agent.keys?.map((key) => (
                                <tr key={key.id}>
                                    <td className="scope-mono">st_{key.prefix}…</td>
                                    <td>
                                        <Pill value={key.revoked_at ? 'REVOKED' : 'ACTIVE'} />
                                    </td>
                                    <td>
                                        <DateTime value={key.last_used_at} />
                                    </td>
                                    <td>
                                        {canManage && !key.revoked_at ? (
                                            <Button variant="outline" size="sm" onClick={() => router.delete(`/agents/${agent.id}/keys/${key.id}`)}>
                                                Revoke key
                                            </Button>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </ScopeLayout>
    );
}
