import { Button } from '@/components/ui/button';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    Bot,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    LayoutDashboard,
    LogOut,
    ShieldCheck,
    SlidersHorizontal,
} from 'lucide-react';
import { ReactNode } from 'react';

export interface ScopeShared extends Record<string, unknown> {
    workspace: { name: string };
    auth: { user: { name: string; email: string } };
    canManage: boolean;
}
export interface Version {
    id: string;
    version: number;
    state: string;
    effect: string;
    reason_code: string;
    conditions: Record<string, Record<string, string | number | (string | number)[]>>;
    published_at: string | null;
}
export interface PolicyRecord {
    id: string;
    name: string;
    status: string;
    published_version_id: string | null;
    versions: Version[];
}
export interface AgentRecord {
    id: string;
    name: string;
    status: string;
    active_keys_count?: number;
    created_at: string;
    keys?: { id: string; prefix: string; revoked_at: string | null; last_used_at: string | null }[];
}
export interface DecisionRecord {
    id: string;
    decision: string;
    reason_code: string;
    evaluated_at: string;
    agent: { id: string; name: string };
    action_request: {
        action_name: string;
        amount_minor: number | null;
        currency: string | null;
        normalized_request_json?: Record<string, unknown>;
        idempotency_key?: string;
    };
    policy_version?: Version | null;
}

export function ScopeLayout({ title, subtitle, action, children }: { title: string; subtitle: string; action?: ReactNode; children: ReactNode }) {
    const { workspace, auth } = usePage<ScopeShared>().props;
    const path = usePage().url.split('?')[0];
    const nav = [
        { name: 'Overview', url: '/dashboard', icon: LayoutDashboard },
        { name: 'Agents', url: '/agents', icon: Bot },
        { name: 'Policies', url: '/policies', icon: SlidersHorizontal },
        { name: 'Decisions', url: '/decisions', icon: Activity },
    ];
    return (
        <div className="scope-app">
            <Head title={title} />
            <a className="sr-only focus:not-sr-only" href="#main">
                Skip to content
            </a>
            <aside className="scope-sidebar">
                <Link href="/dashboard" className="scope-brand">
                    <ShieldCheck size={26} strokeWidth={1.7} />
                    <span>ScopeTwin</span>
                </Link>
                <div className="scope-workspace">
                    <span className="scope-workspace-mark">{workspace.name.slice(0, 1).toUpperCase()}</span>
                    <div>
                        <strong>{workspace.name}</strong>
                        <span>Organization workspace</span>
                    </div>
                </div>
                <p className="scope-nav-label">WORKSPACE</p>
                <nav aria-label="Main navigation">
                    {nav.map((item) => (
                        <Link
                            key={item.url}
                            href={item.url}
                            className={path.startsWith(item.url) ? 'active' : ''}
                            aria-current={path.startsWith(item.url) ? 'page' : undefined}
                        >
                            <item.icon size={18} />
                            {item.name}
                            {path.startsWith(item.url) && <span className="scope-nav-dot" />}
                        </Link>
                    ))}
                </nav>
                <div className="scope-sidebar-bottom">
                    <div className="scope-note">
                        <ShieldCheck size={18} />
                        <strong>Decisions, with boundaries.</strong>
                        <p>Every action is checked against your published policies.</p>
                    </div>
                    <div className="scope-profile">
                        <span>{auth.user.name.slice(0, 1).toUpperCase()}</span>
                        <div>
                            <strong>{auth.user.name}</strong>
                            <small>Workspace member</small>
                        </div>
                        <button type="button" aria-label="Log out" title="Log out" onClick={() => router.post('/logout')}>
                            <LogOut size={17} />
                        </button>
                    </div>
                </div>
            </aside>
            <div className="scope-body">
                <header className="scope-topbar">
                    <span>
                        Workspace <ChevronRight size={13} /> <strong>{title}</strong>
                    </span>
                    <span className="scope-environment">
                        <i />
                        Local sandbox
                    </span>
                    <button className="scope-mobile-logout" type="button" aria-label="Log out" onClick={() => router.post('/logout')}>
                        <LogOut size={16} />
                    </button>
                </header>
                <main id="main" className="scope-main">
                    <div className="scope-heading">
                        <div>
                            <p className="scope-eyebrow">AGENT GOVERNANCE</p>
                            <h1>{title}</h1>
                            <p>{subtitle}</p>
                        </div>
                        {action}
                    </div>
                    {children}
                    <footer className="scope-footer">
                        <span>ScopeTwin / Build 01</span>
                        <span>Policy evaluation only. No external actions executed.</span>
                    </footer>
                </main>
            </div>
        </div>
    );
}

export function Pill({ value }: { value: string }) {
    return (
        <span className={`scope-pill ${value.toLowerCase()}`}>
            <i />
            {value}
        </span>
    );
}
export function DateTime({ value }: { value: string | null }) {
    return <>{value ? new Date(value).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Never'}</>;
}
export function Empty({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="scope-empty">
            <CircleHelp size={28} strokeWidth={1.4} />
            <h3>{title}</h3>
            <p>{children}</p>
        </div>
    );
}
export function DecisionsTable({ rows }: { rows: DecisionRecord[] }) {
    if (!rows.length)
        return (
            <Empty title="Your decision history starts here">
                Create an agent, publish a policy, then send an API request. Every result will appear here.
            </Empty>
        );
    return (
        <div className="scope-table-scroll">
            <table className="scope-table">
                <thead>
                    <tr>
                        <th>Action / agent</th>
                        <th>Amount</th>
                        <th>Decision</th>
                        <th>Reason</th>
                        <th>Evaluated</th>
                        <th>
                            <span className="sr-only">Details</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <td>
                                <Link className="scope-table-title" href={`/decisions/${row.id}`}>
                                    {row.action_request.action_name}
                                </Link>
                                <small>{row.agent.name}</small>
                            </td>
                            <td className="scope-mono">
                                {row.action_request.amount_minor === null
                                    ? '—'
                                    : formatAmount(row.action_request.amount_minor, row.action_request.currency!)}
                                <small>
                                    {row.action_request.amount_minor !== null
                                        ? `${row.action_request.amount_minor} minor units`
                                        : 'No monetary amount'}
                                </small>
                            </td>
                            <td>
                                <Pill value={row.decision} />
                            </td>
                            <td>
                                <span className="scope-reason">{row.reason_code}</span>
                            </td>
                            <td className="scope-nowrap">
                                <DateTime value={row.evaluated_at} />
                            </td>
                            <td>
                                <Link aria-label={`View decision ${row.id}`} href={`/decisions/${row.id}`}>
                                    <ArrowUpRight size={16} />
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
function formatAmount(minor: number, currency: string): string {
    const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency, currencyDisplay: 'code' });
    const digits = formatter.resolvedOptions().maximumFractionDigits ?? 2;
    return formatter.format(minor / 10 ** digits);
}
export function Pagination({ current, last, prev, next }: { current: number; last: number; prev: string | null; next: string | null }) {
    return (
        <div className="scope-pagination">
            <span>
                Page {current} of {last}
            </span>
            <div>
                <Button variant="outline" size="sm" disabled={!prev} onClick={() => prev && router.get(prev)}>
                    <ChevronLeft size={14} />
                    Previous
                </Button>
                <Button variant="outline" size="sm" disabled={!next} onClick={() => next && router.get(next)}>
                    Next
                    <ChevronRight size={14} />
                </Button>
            </div>
        </div>
    );
}
export async function issueKey(url: string, body: Record<string, string> = {}) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok)
        throw new Error(
            Object.values(data.errors ?? {})
                .flat()
                .join(' ') ||
                data.message ||
                'Request failed.',
        );
    return data as { token: string; agent_id?: string };
}
