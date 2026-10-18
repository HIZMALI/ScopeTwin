import { createRoot } from 'react-dom/client';
import '../css/public.css';

type Demo = {
    sampleCount: number;
    changed: number;
    newlyDenied: number;
    baselineFingerprint: string;
    candidateFingerprint: string;
    rows: {
        action: string;
        amount: number | null;
        original: string;
        baseline: string;
        candidate: string;
        changed: boolean;
        originalReason: string;
        candidateReason: string;
    }[];
    approval: { status: string; decision: string; executionPerformed: boolean } | null;
};
const root = document.getElementById('public-root')!;
const payload = JSON.parse(root.dataset.payload!) as { page: string; demo?: Demo | null };
const Badge = ({ value }: { value: string }) => <span className={`tour-badge tour-${value.toLowerCase()}`}>{value}</span>;

function App() {
    const demo = payload.demo;
    const isDemo = payload.page === 'demo';
    return (
        <div className="public-site">
            <a className="tour-skip" href="#main">
                Skip to content
            </a>
            <header className="tour-header">
                <a className="tour-brand" href="/">
                    <span className="tour-mark" aria-hidden="true">
                        S<span>T</span>
                    </span>
                    ScopeTwin
                </a>
                <nav aria-label="Main navigation">
                    <a href="/#workflow">How it works</a>
                    <a href="/#architecture">Architecture</a>
                    <a href="/#pricing">Pricing</a>
                    <a href="/login">Sign in</a>
                    <a className="tour-button small" href={isDemo ? '/register' : '/demo'}>
                        {isDemo ? 'Create workspace' : 'Try read-only demo'} <span aria-hidden="true">↗</span>
                    </a>
                </nav>
            </header>
            <main id="main" tabIndex={-1}>
                {isDemo ? (
                    <>
                        <section className="tour-intro">
                            <span className="tour-eyebrow">JUDGE WALKTHROUGH / READ ONLY</span>
                            <h1>
                                One change.
                                <br />
                                Every consequence, visible.
                            </h1>
                            <p>
                                A persisted synthetic scenario, evaluated by the same deterministic engine as the live API. Explore without an account
                                or an API key.
                            </p>
                            <div className="tour-labels">
                                <span>Synthetic demo data</span>
                                <span>No external execution</span>
                                <span>No public mutations</span>
                            </div>
                        </section>
                        {!demo ? (
                            <section className="tour-section tour-unavailable">
                                <h2>Demo is not configured</h2>
                                <p>This installation has no enabled, isolated judge dataset. No sample metrics are being presented.</p>
                                <a className="tour-button" href="/register">
                                    Create your own workspace
                                </a>
                                <p className="tour-muted">The operator can enable the dedicated demo environment using the deployment runbook.</p>
                            </section>
                        ) : (
                            <>
                                <nav className="tour-steps" aria-label="Walkthrough">
                                    <a href="#recorded">01 / Recorded requests</a>
                                    <a href="#replay">02 / Compare the draft</a>
                                    <a href="#review">03 / Human review</a>
                                </nav>
                                <section className="tour-section" id="recorded">
                                    <div className="tour-section-heading">
                                        <span className="tour-eyebrow">01 / EVALUATE</span>
                                        <h2>Three requests. Explicit boundaries.</h2>
                                        <p>
                                            The published rules allow USD refunds up to $500 and require review above $500. Unknown actions are denied
                                            by default. Amounts use integer minor units.
                                        </p>
                                    </div>
                                    <div className="tour-code-grid">
                                        <div>
                                            <h3>Example request · credential omitted</h3>
                                            <pre>
                                                <code>
                                                    {
                                                        'POST /api/v1/agent/actions/evaluate\nIdempotency-Key: <unique-request-id>\n\n{\n  "action_name": "refund.issue",\n  "amount_minor": 12000,\n  "currency": "USD"\n}'
                                                    }
                                                </code>
                                            </pre>
                                        </div>
                                        <div>
                                            <h3>Persisted result · selected safe fields</h3>
                                            <pre>
                                                <code>
                                                    {JSON.stringify(
                                                        {
                                                            decision: demo.rows.find((r) => r.amount === 12000)?.original,
                                                            reason_code: demo.rows.find((r) => r.amount === 12000)?.originalReason,
                                                            approval_required: false,
                                                        },
                                                        null,
                                                        2,
                                                    )}
                                                </code>
                                            </pre>
                                            <p>No bearer token is needed to view this tour. The authenticated API remains protected.</p>
                                        </div>
                                    </div>
                                </section>
                                <section className="tour-section" id="replay">
                                    <div className="tour-section-heading">
                                        <span className="tour-eyebrow">02 / REPLAY LAB</span>
                                        <h2>Test the draft before publishing.</h2>
                                        <p>
                                            The unpublished candidate denies USD refunds above $100. The report freezes the recorded inputs, current
                                            published baseline and candidate draft. Nothing is executed.
                                        </p>
                                    </div>
                                    <div className="tour-metrics">
                                        <div>
                                            <strong>{demo.sampleCount}</strong>
                                            <span>Persisted requests</span>
                                        </div>
                                        <div>
                                            <strong>{demo.changed}</strong>
                                            <span>Changed vs baseline</span>
                                        </div>
                                        <div>
                                            <strong>{demo.newlyDenied}</strong>
                                            <span>Newly denied</span>
                                        </div>
                                        <div>
                                            <strong>0</strong>
                                            <span>External actions executed</span>
                                        </div>
                                    </div>
                                    <div className="tour-table-wrap" tabIndex={0} role="region" aria-label="Three-way replay results">
                                        <table>
                                            <caption>Recorded / baseline / candidate · synthetic USD scenarios</caption>
                                            <thead>
                                                <tr>
                                                    <th scope="col">Action / amount</th>
                                                    <th scope="col">Recorded</th>
                                                    <th scope="col">Baseline</th>
                                                    <th scope="col">Candidate draft</th>
                                                    <th scope="col">Effect changed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {demo.rows.map((r, i) => (
                                                    <tr key={i}>
                                                        <th scope="row">
                                                            {r.action}
                                                            <small>
                                                                {r.amount === null
                                                                    ? 'No monetary amount'
                                                                    : new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(
                                                                          r.amount / 100,
                                                                      )}
                                                            </small>
                                                        </th>
                                                        <td>
                                                            <Badge value={r.original} />
                                                        </td>
                                                        <td>
                                                            <Badge value={r.baseline} />
                                                        </td>
                                                        <td>
                                                            <Badge value={r.candidate} />
                                                            <small>{r.candidateReason}</small>
                                                        </td>
                                                        <td>{r.changed ? 'Yes' : 'No'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <p className="tour-note">
                                        Two changes means two decision labels changed from the baseline. It is not evidence of money saved or losses
                                        prevented. DENY takes precedence over REVIEW, then ALLOW; no matching rule means DENY.
                                    </p>
                                    <details>
                                        <summary>Inspect frozen evidence fingerprints</summary>
                                        <dl className="tour-fingerprints">
                                            <dt>Published baseline · SHA-256</dt>
                                            <dd>{demo.baselineFingerprint}</dd>
                                            <dt>Candidate draft · SHA-256</dt>
                                            <dd>{demo.candidateFingerprint}</dd>
                                        </dl>
                                        <p>These identify stored policy definitions. Editing a future draft does not rewrite this report.</p>
                                    </details>
                                </section>
                                <section className="tour-section" id="review">
                                    <div className="tour-section-heading">
                                        <span className="tour-eyebrow">03 / HUMAN REVIEW</span>
                                        <h2>A recorded approval. No refund executed.</h2>
                                        <p>
                                            The original $2,400 result remains REVIEW. A synthetic operator documented a decision while its policy and
                                            credential context were valid.
                                        </p>
                                    </div>
                                    <div className="tour-approval">
                                        <div>
                                            <span>Original decision</span>
                                            <Badge value={demo.approval?.decision ?? 'UNAVAILABLE'} />
                                        </div>
                                        <div>
                                            <span>Documented status</span>
                                            <Badge value={demo.approval?.status ?? 'UNAVAILABLE'} />
                                        </div>
                                        <div>
                                            <span>Execution performed</span>
                                            <strong>{demo.approval?.executionPerformed === false ? 'False' : 'Unavailable'}</strong>
                                        </div>
                                    </div>
                                    <p className="tour-note">
                                        Live approvals expire after 24 hours. Changed policies, a revoked originating key or a disabled agent
                                        invalidate pending review. Visitors cannot approve, publish, issue keys or submit actions here.
                                    </p>
                                </section>
                                <section className="tour-cta">
                                    <div>
                                        <span className="tour-eyebrow">FROM TOUR TO YOUR WORKSPACE</span>
                                        <h2>Make the next policy change explainable.</h2>
                                        <p>Connect your agent to the evaluation API. Keep execution under your own control.</p>
                                    </div>
                                    <a className="tour-button" href="/register">
                                        Create workspace <span aria-hidden="true">↗</span>
                                    </a>
                                </section>
                            </>
                        )}
                    </>
                ) : (
                    <>
                        <section className="tour-hero">
                            <div>
                                <span className="tour-eyebrow">POLICY CONTROL FOR AGENT TOOL ACTIONS</span>
                                <h1>
                                    Know what your
                                    <br />
                                    policy changes.
                                </h1>
                                <p>
                                    Replay a proposed rule against recorded requests before it goes live. Give engineering and operations one
                                    traceable answer: allow, review or deny.
                                </p>
                                <div className="tour-actions">
                                    <a className="tour-button" href="/demo">
                                        Try read-only demo <span aria-hidden="true">↗</span>
                                    </a>
                                    <a className="tour-button secondary" href="/register">
                                        Create workspace
                                    </a>
                                </div>
                                <p className="tour-muted">Deterministic decisions. Human review. No external tool execution.</p>
                            </div>
                            <div className="tour-system" aria-label="Policy change workflow">
                                <div className="tour-system-top">
                                    <span>SCOPETWIN / CONTROL PLANE</span>
                                    <span className="tour-live">Deterministic</span>
                                </div>
                                <div className="tour-system-step">
                                    <span>01</span>
                                    <div>
                                        <strong>Record the request</strong>
                                        <small>Typed input · integer amounts · scoped identity</small>
                                    </div>
                                </div>
                                <div className="tour-system-line" />
                                <div className="tour-system-step">
                                    <span>02</span>
                                    <div>
                                        <strong>Replay the proposed policy</strong>
                                        <small>Recorded → baseline → candidate</small>
                                    </div>
                                </div>
                                <div className="tour-system-line" />
                                <div className="tour-system-step">
                                    <span>03</span>
                                    <div>
                                        <strong>Review the evidence</strong>
                                        <small>Immutable snapshots · documentary approval</small>
                                    </div>
                                </div>
                                <div className="tour-system-outcomes">
                                    <Badge value="ALLOW" />
                                    <Badge value="REVIEW" />
                                    <Badge value="DENY" />
                                </div>
                                <a href="/demo/replay">
                                    Open the actual stored replay <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        </section>
                        <section className="tour-section" id="workflow">
                            <div className="tour-section-heading">
                                <span className="tour-eyebrow">A SMALLER GAP BETWEEN INTENT AND IMPACT</span>
                                <h2>Change rules with evidence.</h2>
                                <p>
                                    Built for SaaS engineering leads and automation consultants who need clear limits around support and operations
                                    agents.
                                </p>
                            </div>
                            <div className="tour-cards">
                                {[
                                    [
                                        '01 / DEFINE',
                                        'Set explicit boundaries',
                                        'Publish typed, versioned rules for actions, amounts, currencies and destination domains. Unmatched requests are denied.',
                                    ],
                                    [
                                        '02 / COMPARE',
                                        'Replay before promotion',
                                        'Use the same engine on recorded requests. Compare historical decisions, the current baseline and an unpublished candidate.',
                                    ],
                                    [
                                        '03 / DOCUMENT',
                                        'Keep review accountable',
                                        'Inspect reasons and frozen evidence. Record a human decision with expiry and policy freshness checks.',
                                    ],
                                ].map(([n, h, p]) => (
                                    <article key={n}>
                                        <span className="tour-eyebrow">{n}</span>
                                        <h3>{h}</h3>
                                        <p>{p}</p>
                                    </article>
                                ))}
                            </div>
                            <a className="tour-text-link" href="/demo">
                                See the three-step judge walkthrough →
                            </a>
                        </section>
                        <section className="tour-section tour-architecture" id="architecture">
                            <div>
                                <span className="tour-eyebrow">CLEAR RESPONSIBILITY, AUDITABLE BOUNDARIES</span>
                                <h2>
                                    Policy is deterministic.
                                    <br />
                                    Execution stays with you.
                                </h2>
                                <p>
                                    One Laravel application with React, Inertia and PostgreSQL. Identity-derived workspace scope, one-time hashed
                                    agent keys, transactional idempotency and bounded replay.
                                </p>
                                <p>
                                    No LLM grants permissions. No billing provider or action executor is connected. Application-level protections are
                                    tested; no independent security certification is claimed.
                                </p>
                            </div>
                            <ol>
                                <li>
                                    <strong>Agent / browser</strong>
                                    <span>Scoped API key or session + CSRF</span>
                                </li>
                                <li>
                                    <strong>Pure policy evaluator</strong>
                                    <span>DENY → REVIEW → ALLOW · default deny</span>
                                </li>
                                <li>
                                    <strong>PostgreSQL evidence</strong>
                                    <span>Decisions · frozen replay · approval audit</span>
                                </li>
                            </ol>
                        </section>
                        <section className="tour-section" id="pricing">
                            <div className="tour-section-heading">
                                <span className="tour-eyebrow">PRICING HYPOTHESIS</span>
                                <h2>A control layer teams can budget for.</h2>
                                <p>
                                    Proposed plans for validation with prospective buyers. No subscriptions, paid entitlements, checkout or charges
                                    are active.
                                </p>
                            </div>
                            <div className="tour-cards tour-pricing">
                                {[
                                    ['Free', '$0', 'Explore the workflow in a sandbox.'],
                                    ['Team', '$79', 'A proposed plan for growing agent teams.'],
                                    ['Business', '$299', 'A proposed plan for larger operational needs.'],
                                ].map(([n, price, p]) => (
                                    <article key={n}>
                                        <h3>{n}</h3>
                                        <div>
                                            <strong>{price}</strong>
                                            <span> / month · proposed</span>
                                        </div>
                                        <p>{p}</p>
                                        <a href="/register">Create a workspace →</a>
                                    </article>
                                ))}
                            </div>
                        </section>
                        <section className="tour-cta">
                            <div>
                                <span className="tour-eyebrow">SEE THE DECISION CHANGE</span>
                                <h2>Three requests. Two changed outcomes.</h2>
                                <p>A real stored synthetic replay. No sign-in or secrets required.</p>
                            </div>
                            <a className="tour-button" href="/demo/replay">
                                Explore the replay <span aria-hidden="true">↗</span>
                            </a>
                        </section>
                    </>
                )}
            </main>
            <footer className="tour-footer">
                <a className="tour-brand" href="/">
                    ScopeTwin
                </a>
                <p>Evaluate. Replay. Review. No external execution.</p>
                <nav aria-label="Footer">
                    <a href="/demo">Read-only demo</a>
                    <a href="/login">Sign in</a>
                    <a href="/register">Create workspace</a>
                </nav>
            </footer>
        </div>
    );
}

createRoot(root).render(<App />);
