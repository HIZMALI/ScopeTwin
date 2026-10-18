import { DateTime, Pill, PolicyRecord, ScopeLayout, ScopeShared } from '@/components/scope';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Row = { field: string; op: string; value: string };
const fields = ['action_name', 'amount_minor', 'currency', 'destination_domain', 'resource_type'];
const labels: Record<string, string> = {
    action_name: 'Action name',
    amount_minor: 'Amount (minor units)',
    currency: 'Currency',
    destination_domain: 'Destination domain',
    resource_type: 'Resource type',
};
const operators: Record<string, string> = { eq: 'equals', in: 'is one of', gt: 'greater than', gte: 'at least', lt: 'less than', lte: 'at most' };
export default function PolicyForm({ policy, currencies }: { policy: PolicyRecord | null; currencies: string[] }) {
    const { canManage } = usePage<ScopeShared>().props;
    const latest = policy?.versions[0];
    const draft = policy?.versions.find((v) => v.state === 'DRAFT');
    const [rows, setRows] = useState<Row[]>(
        latest
            ? Object.entries(latest.conditions).map(([field, expr]) => ({
                  field,
                  op: Object.keys(expr)[0],
                  value: Array.isArray(Object.values(expr)[0])
                      ? (Object.values(expr)[0] as (string | number)[]).join(', ')
                      : String(Object.values(expr)[0]),
              }))
            : [{ field: 'action_name', op: 'eq', value: '' }],
    );
    const form = useForm({
        name: policy?.name ?? '',
        effect: latest?.effect ?? 'ALLOW',
        reason_code: latest?.reason_code ?? '',
        status: policy?.status ?? 'ACTIVE',
        conditions: {},
    });
    const [saved, setSaved] = useState(true);
    const [publishing, setPublishing] = useState(false);
    const [replaying, setReplaying] = useState(false);
    const [replayError, setReplayError] = useState('');
    function update(index: number, patch: Partial<Row>) {
        setSaved(false);
        setRows(rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));
    }
    return (
        <ScopeLayout
            title={policy ? policy.name : 'Create policy'}
            subtitle="Use a typed rule to define exactly when an action is allowed, reviewed, or denied."
        >
            <div className="scope-policy-grid">
                <section className="scope-panel scope-form-panel">
                    <h2>{policy ? 'Policy editor' : 'New policy'}</h2>
                    <p>Save a draft, inspect its conditions, then publish it to live evaluations.</p>
                    <form
                        className="scope-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const conditions = Object.fromEntries(
                                rows.map((r) => {
                                    const cast = (v: string) => (r.field === 'amount_minor' ? Number(v.trim()) : v.trim());
                                    return [r.field, { [r.op]: r.op === 'in' ? r.value.split(',').map(cast) : cast(r.value) }];
                                }),
                            );
                            form.transform((d) => ({ ...d, conditions }));
                            const options = { onSuccess: () => setSaved(true) };
                            if (policy) form.put(`/policies/${policy.id}`, options);
                            else form.post('/policies', options);
                        }}
                    >
                        <fieldset disabled={!canManage || form.processing}>
                            <div className="scope-form">
                                <div>
                                    <Label htmlFor="policy-name">Policy name</Label>
                                    <Input
                                        id="policy-name"
                                        required
                                        maxLength={100}
                                        placeholder="e.g. Standard refunds"
                                        value={form.data.name}
                                        onChange={(e) => {
                                            form.setData('name', e.target.value);
                                            setSaved(false);
                                        }}
                                    />
                                    <p className="scope-error">{form.errors.name}</p>
                                </div>
                                <div className="scope-two-col">
                                    <div>
                                        <Label htmlFor="effect">Decision effect</Label>
                                        <select
                                            id="effect"
                                            value={form.data.effect}
                                            onChange={(e) => {
                                                form.setData('effect', e.target.value);
                                                setSaved(false);
                                            }}
                                        >
                                            <option>ALLOW</option>
                                            <option>REVIEW</option>
                                            <option>DENY</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="policy-status">Policy status</Label>
                                        <select
                                            id="policy-status"
                                            value={form.data.status}
                                            onChange={(e) => {
                                                form.setData('status', e.target.value);
                                                setSaved(false);
                                            }}
                                        >
                                            <option>ACTIVE</option>
                                            <option>DISABLED</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="reason">Reason code</Label>
                                    <Input
                                        id="reason"
                                        required
                                        maxLength={64}
                                        pattern="[A-Z][A-Z0-9_]*"
                                        placeholder="REFUND_WITHIN_LIMIT"
                                        value={form.data.reason_code}
                                        onChange={(e) => {
                                            form.setData('reason_code', e.target.value);
                                            setSaved(false);
                                        }}
                                    />
                                    <p>Stable, uppercase code returned in the API and decision log.</p>
                                    <p className="scope-error">{form.errors.reason_code}</p>
                                </div>
                                <div className="scope-condition-heading">
                                    <h3>Match conditions</h3>
                                    <span>Match all (AND)</span>
                                </div>
                                {rows.map((row, i) => (
                                    <div className="scope-condition" key={i}>
                                        <div>
                                            <Label htmlFor={`field-${i}`}>Field</Label>
                                            <select
                                                id={`field-${i}`}
                                                value={row.field}
                                                disabled={row.field === 'action_name'}
                                                onChange={(e) => update(i, { field: e.target.value, op: 'eq', value: '' })}
                                            >
                                                {fields
                                                    .filter((f) => f === row.field || !rows.some((r) => r.field === f))
                                                    .map((f) => (
                                                        <option key={f} value={f}>
                                                            {labels[f]}
                                                        </option>
                                                    ))}
                                            </select>
                                        </div>
                                        <div>
                                            <Label htmlFor={`op-${i}`}>Operator</Label>
                                            <select id={`op-${i}`} value={row.op} onChange={(e) => update(i, { op: e.target.value })}>
                                                {(row.field === 'amount_minor' ? Object.keys(operators) : ['eq', 'in']).map((op) => (
                                                    <option key={op} value={op}>
                                                        {operators[op]}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <Label htmlFor={`value-${i}`}>Value</Label>
                                            <Input
                                                id={`value-${i}`}
                                                required
                                                value={row.value}
                                                placeholder={
                                                    row.field === 'amount_minor'
                                                        ? '50000'
                                                        : row.field === 'action_name'
                                                          ? 'refund.issue'
                                                          : row.field === 'currency'
                                                            ? 'USD'
                                                            : 'Value'
                                                }
                                                onChange={(e) => update(i, { value: e.target.value })}
                                            />
                                        </div>
                                        {row.field !== 'action_name' && (
                                            <button
                                                className="scope-remove"
                                                type="button"
                                                aria-label={`Remove ${labels[row.field]}`}
                                                onClick={() => {
                                                    setRows(rows.filter((_, j) => i !== j));
                                                    setSaved(false);
                                                }}
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        )}
                                    </div>
                                ))}
                                <p>
                                    Use comma-separated values for “is one of”. Amounts must be whole minor units. Supported currencies:{' '}
                                    {currencies.join(', ')}.
                                </p>
                                <p className="scope-error" role="alert">
                                    {form.errors.conditions}
                                </p>
                                {rows.length < 5 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="scope-add-condition"
                                        onClick={() => {
                                            setRows([...rows, { field: fields.find((f) => !rows.some((r) => r.field === f))!, op: 'eq', value: '' }]);
                                            setSaved(false);
                                        }}
                                    >
                                        <Plus size={15} />
                                        Add condition
                                    </Button>
                                )}
                                {canManage && (
                                    <div className="scope-form-actions">
                                        <Button type="submit" disabled={form.processing}>
                                            {form.processing ? 'Saving…' : 'Save draft'}
                                        </Button>
                                        <span>Saving does not publish changes.</span>
                                    </div>
                                )}
                            </div>
                        </fieldset>
                    </form>
                </section>
                <div className="scope-policy-aside">
                    <section className="scope-panel scope-form-panel">
                        <h2>Publication</h2>
                        <p>{policy?.published_version_id ? 'A published snapshot is currently live.' : 'This policy is not live yet.'}</p>
                        {latest && (
                            <div className="scope-version-summary">
                                <Pill value={latest.state} />
                                <span>Version {latest.version}</span>
                            </div>
                        )}
                        <p>Published snapshots are immutable. Editing creates a new draft while the current version stays live.</p>
                        {canManage && (
                            <Button
                                className="w-full"
                                disabled={!draft || !saved || form.processing || publishing || form.data.status !== 'ACTIVE'}
                                onClick={() => {
                                    setPublishing(true);
                                    router.post(`/policies/${policy!.id}/publish`, { draft_id: draft!.id }, { onFinish: () => setPublishing(false) });
                                }}
                            >
                                {publishing ? 'Publishing…' : 'Publish saved draft'}
                            </Button>
                        )}
                        {!saved && <p>Save your changes before publishing.</p>}
                        {canManage && (
                            <Button
                                className="mt-3 w-full"
                                variant="outline"
                                disabled={!draft || !saved || form.processing || replaying || form.data.status !== 'ACTIVE'}
                                onClick={() => {
                                    setReplaying(true);
                                    setReplayError('');
                                    router.post(
                                        '/replays',
                                        { draft_id: draft!.id, limit: 100 },
                                        { onFinish: () => setReplaying(false), onError: (errors) => setReplayError(Object.values(errors).join(' ')) },
                                    );
                                }}
                            >
                                {replaying ? 'Running replay…' : 'Preview impact'}
                            </Button>
                        )}
                        {replayError && (
                            <p className="scope-error" role="alert">
                                {replayError}
                            </p>
                        )}
                    </section>
                    <section className="scope-panel scope-form-panel">
                        <h2>Version history</h2>
                        {policy?.versions.map((v) => (
                            <div className="scope-version" key={v.id}>
                                <div>
                                    <strong>v{v.version}</strong>
                                    <Pill value={v.state} />
                                </div>
                                <p>
                                    {v.effect} · {v.reason_code}
                                </p>
                                <small>{v.published_at ? <DateTime value={v.published_at} /> : 'Not published'}</small>
                            </div>
                        )) ?? <p>Your first version will appear after saving.</p>}
                    </section>
                </div>
            </div>
        </ScopeLayout>
    );
}
