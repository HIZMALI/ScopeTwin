# SCOPE TWIN — BUILD 02: REPLAY LAB + APPROVAL WORKFLOW

**Execution brief for Codex — 18 October 2026**  
**Working directory:** `C:\Projects\ScopeTwin`  
**Expected branch:** `feat/build-02-replay-approvals`  
**Product/UI language:** English. **Final implementation report:** Turkish.  
**Priority:** A competition-grade working product, not superficial screens.

## 0. Operating instructions

You are implementing Build 02 of ScopeTwin **inside the existing Laravel 13 / React 19 / Inertia / TypeScript / PostgreSQL application**. Build 01 has reportedly passed 89 PHP tests / 537 assertions on SQLite and PostgreSQL, and a real-HTTP Chromium browser journey. These counts are a baseline to preserve, not a substitute for executing tests yourself.

1. Read `AGENTS.md`, the project planning files, `README.md`, `docs/IMPLEMENTATION_LOG.md`, and the actual Build 01 models, migrations, controllers, evaluator, routes, frontend and tests. The actual repository code is authoritative if any path here differs.
2. Confirm the working tree is clean and the current commit. Create a **local feature branch** `feat/build-02-replay-approvals` from the current local `main` (`bf3f0eb` was reported). If there are uncommitted user changes, preserve them and do not overwrite; report the issue and continue safely where possible.
3. Do not ask for implementation preferences or stop after planning. Inspect, implement, run tests, fix failures, and complete the main user workflows.
4. Preserve the existing `.env`, `APP_KEY`, `DB_PASSWORD`, development PostgreSQL data, port `18731`, and isolated Compose service. Do not reset/seed/rollback the user's application database. Use disposable dedicated test databases for destructive test operations.
5. Do not push to remote, deploy, purchase services, call real payment/refund APIs, create a paid subscription, or publish actual customer data. No remote repository exists yet.
6. Do not claim security certification. Do not weaken security middleware, tenant checks, immutability, idempotency, or existing behavior to satisfy tests.
7. Do not store or print plaintext API keys, tokens, application keys, database passwords, personal data, or secrets. Screenshots and browser traces must mask/hide keys as in Build 01.
8. Use actual end-to-end data throughout the UI. No fake metrics, decorative charts, nonfunctional buttons, or hardcoded demo outcomes. Synthetic **test fixtures** are fine when labelled as such.
9. Prioritize the P0 requirements below. If time is limited, complete and verify P0 before P1. Log any omission precisely.

## 1. Product promise and non-negotiable invariants

**ScopeTwin prevents uncontrolled AI-agent tool operations through deterministic policies, and lets a company simulate a policy change against past requests *before* publishing it.**

- ScopeTwin evaluates action requests. It **never actually issues refunds, sends data, transfers money, executes tools, or calls a live external service**.
- Existing `POST /api/v1/agent/actions/evaluate` request/response and all Build 01 guarantees remain backward-compatible.
- Policy decision precedence remains **DENY > REVIEW > ALLOW**, with `DEFAULT_DENY` if nothing matches. Never create a second, divergent evaluator.
- Published policy snapshots and historical action requests/decisions are not modified by replay or approvals.
- A stored `REVIEW` decision is **not** permission to execute a tool. An administrator approving a request means a documented approval status only. There is no payment/refund/execution capability.
- Every database read/write is organization-scoped. Browser context comes from authenticated membership, agent API context from a verified agent key; never accept an organization ID from a form/API body as an authority source.
- Audit records must not contain token material, full arbitrary prompt text or other free-text PII.

## 2. P0 — Replay Lab, real comparison against draft policy

### 2.1 Core use case

An OWNER or ADMIN edits an **unpublished draft** of an ACTIVE policy (or creates a new draft policy). From the policy editor, they click **Preview impact**; alternatively they open **Replay Lab** and select a candidate draft.

The application evaluates a bounded selection of historical, **normalized** action requests from their organization, using the **existing pure `PolicyEvaluator`**, under two hypothetical policy sets:

- **Baseline:** the complete set of current ACTIVE, published policy snapshots at the instant the replay is created.
- **Candidate:** same baseline, but the selected policy is replaced by its validated unpublished draft; if it is an entirely new unpublished policy, add its validated draft to that set. Other active policies, priority rules and default-deny behavior stay unchanged.

Never publish the draft as part of replay. Disabled policies must not silently reappear. If the current code's draft representation requires an adapter, make an immutable in-memory evaluator input from the existing validated DSL; do not create a fake published `policy_version` row.

### 2.2 Three outcomes must be clearly distinguished

For every sampled historical request persist/display:

1. **Original recorded decision**, made at the historical time (can be from an older policy set).
2. **Baseline decision**, re-evaluated with today's frozen current published policy set.
3. **Candidate decision**, simulated with that set plus the selected draft.

This distinction prevents the false claim that a draft alone caused a change that actually came from a previous policy publication. The main impact metric is **candidate compared with baseline**, not candidate compared with original.

Persist `decision`, `reason_code`, and matched policy identifiers for baseline and candidate, with an explicit draft marker/fingerprint for a draft match (do not invent a published version ID).

### 2.3 Replay inputs and bounds

- Historical data source: sanitized existing `action_requests` from the active organization, linked to recorded decisions. Use the persisted normalized attributes; do not require external HTTP calls or replay live API traffic.
- Default to the latest **100** historical requests, capped at **500** per run. Support optional agent, action and date filters only if implementable without weakening scope checks. Enforce bounds server-side.
- If there are no historical actions, show a helpful empty state, with a link to how to generate a real API test request. Do not make up data.
- For a draft missing, malformed, disabled or foreign to the organization, return a scoped validation/404 response without publishing anything.
- Throttle replay creation per user/organization, use indexed/bounded queries, and avoid N+1 evaluation queries. Keep the synchronous path simple and bounded; a background queue is not necessary for a 500-item MVP if performance is acceptable.
- Collect baseline versions, validated candidate contents and selected request IDs consistently when starting a run. Save a reproducible snapshot manifest/fingerprints and a candidate draft snapshot in replay metadata so a later policy edit does not rewrite past replay output.

### 2.4 Persistence (adapt exact names to existing conventions)

Add organization-scoped tables equivalent to:

`replay_runs`: UUID, organization_id, created_by_user_id, candidate_policy_id, candidate_source (`DRAFT` for P0), baseline policy manifest and fingerprint, frozen validated candidate policy/DSL and fingerprint, filters/sample count, aggregate counts/transition counts, created_at, completed_at, status.

`replay_results`: UUID, replay_run_id, organization_id, historical_action_request_id, historical_policy_decision_id, `original_effect`, `baseline_effect`, `candidate_effect`, original/baseline/candidate reason codes and matched references, transition enum or derived comparison, timestamp.

Enforce foreign keys, organization indexes, useful unique constraints, and reasonable JSON sizes. Stored snapshots can contain only validated policy definitions and allowed normalized fields, never secret keys.

A replay may write **only** its own replay run/results and an appropriately sanitized audit event. It must not create/edit action requests, live policy decisions, published versions, approval records, or live API events. Verify this explicitly with before/after assertions.

### 2.5 Required user interface

- Add navigation **Replay Lab**; a history screen with actual runs, actor/time, candidate policy, request count, and outcome changes.
- Add a **Preview impact** action beside a valid draft in Policies. It must run a real backend simulation and navigate to the created replay report.
- Replay report: clear title (e.g. `Draft policy impact`), baseline and candidate summary, three-way outcomes where relevant, a compact transition visualization or matrix, counts `changed`, `newly allowed`, `newly reviewed`, `newly denied`, and a searchable/filterable detail table of sampled requests.
- Every row can open detail with original, baseline and candidate outcomes and reasons. Make it clear what was recorded versus simulated.
- Add user-friendly explanation of why the change occurred and the matched rule, derived from deterministic reason codes, not fabricated LLM output.
- Present an actual `No changes detected` state when zero baseline-to-candidate changes occur. Candidate replay must be useful even if there are zero changes.
- Preserve the existing professional, readable English design; ensure desktop 1440 px and mobile 390 px layouts do not overflow horizontally.

### 2.6 Replay demonstration scenario

Create test fixtures (and optionally a local/testing-only *explicit* demo CLI) for:

- Existing published ALLOW: `refund.issue` with USD amount `<= 50000` ($500) → ALLOW.
- Existing published REVIEW: `refund.issue` with USD amount `> 50000` → REVIEW.
- Unknown action → DEFAULT_DENY.
- Historical requests: `refund.issue` for `$120` (`amount_minor: 12000`); `refund.issue` for `$2,400` (`240000`); and `unknown.action`.
- Create a **new unpublished draft DENY** rule for USD `refund.issue` amounts `> 10000` ($100).
- Baseline outcomes: ALLOW, REVIEW, DENY. Candidate outcomes: DENY, DENY, DENY. Exactly two baseline-to-candidate changes.
- No draft publication or live decision change occurs during replay.

The numeric thresholds are expressed in integer minor units; UI formats them as USD.

## 3. P0 — Real authorized human approval queue

### 3.1 Transactional lifecycle

For a **new** API evaluation yielding `REVIEW`, create exactly one `PENDING` approval request, atomically linked to its immutable policy decision and organization/agent/action. Do this in the same transaction as the request/decision/audit event. An idempotent API retry must return the original response and **not** create a duplicate approval.

The approval record stores the decision reference, sanitized action summary, organization, creation timestamp, 24-hour expiry, and a deterministic fingerprint/manifest of the ACTIVE published policy snapshot set used to make the decision. The system must be able to detect relevant policy state changes before approval.

Lifecycle:

`PENDING → APPROVED | REJECTED | STALE | EXPIRED`

- OWNER / ADMIN may approve or reject; VIEWER is read-only.
- One atomic terminal transition only. Two simultaneous reviewers cannot both successfully decide; subsequent conflicting transitions return a clear `409 Conflict`.
- If 24 hours have elapsed, approval cannot succeed and must be presented as expired. Avoid needing a cron job solely to prevent expired approval (validate time when mutating).
- If the active policy snapshot manifest (including disabled/enabled membership) differs from the manifest recorded at evaluation, do not approve a stale request; mark it `STALE` and instruct the user to request a fresh evaluation. Changing an unpublished draft alone must not invalidate it.
- Rejecting does not alter the original `REVIEW` decision or execute any action.
- Record who changed the approval status, when, and the sanitized audit event. A short optional operator note is not needed for this MVP; **do not add unnecessary required explanation/reason fields**.
- For old `REVIEW` decisions created in Build 01, **do not backfill them as actionable PENDING approvals using today's policy fingerprint**. Their historical policy context may be incomplete. Show them only as read-only historical review decisions or explicitly label them `reevaluation required`.

### 3.2 UX and API

- Add sidebar **Approvals** with real Pending / Resolved filters, badges/counts, timestamps, agent, action, sanitized amount/currency and original decision reason.
- Detail screen shows policy context and safe normalized action summary; includes **Approve** and **Reject** with pending confirmation/disabled states and clear success/error feedback.
- Users must never believe approval executed a refund, transferred money or called an external API. Clearly state `Approved in ScopeTwin. No external action was executed.`
- Prefer a scoped, authenticated **read-only approval status lookup** for the originating agent using its verified API key and its own action/decision ID, if it can be implemented without exposing another agent's data. The lookup must never execute the tool or return any secret. If it threatens P0 completion, document as a later enhancement; UI queue is required now.
- Enforce canonical UUID constraints, CSRF for browser mutations, role permissions and org ownership on every endpoint, and correct 403/404/409 responses without cross-tenant data disclosure.

## 4. P1 — Usage and pricing presentation (after P0 passes)

- **Usage & Plans** page with genuine activity numbers computed from existing stored action/decision records: request counts by day, agent, ALLOW/REVIEW/DENY; approvals and replay usage may be shown if backed by queries.
- Show **Free sandbox**, **Team $79/month**, **Business $299/month** clearly labelled `Proposed pricing` / `Pricing preview — no charges`, with honest hypothetical plan boundaries.
- No checkout button that purports to work unless a genuine test-only checkout has been fully integrated and verified. Billing is **not live** in Build 02.
- Keep summary queries indexed, org-scoped and bounded. Avoid storing duplicated fabricated revenue data.

## 5. Engineering/security acceptance criteria

Implement meaningful automated tests for every item below. Preserve *all* existing Build 01 tests.

**Replay behavior**
- Three-way recorded/baseline/candidate semantics are correct when policies have changed since the original decision.
- Draft substitution for an active published policy; addition of a new unpublished policy; malformed draft rejected.
- DENY > REVIEW > ALLOW and DEFAULT_DENY preserved, including overlapping rules and exact USD minor-unit boundaries.
- Two changed decisions in the required demo fixture, with unchanged unknown-action default deny.
- Run results remain unchanged after the draft is edited/published/disabled later.
- Running a replay leaves live `action_requests`, `policy_decisions`, `policy_versions`, agent tokens and approval rows unchanged (audit and replay tables excepted).
- No cross-tenant sample, run, policy, detail, replay result or foreign UUID access, including direct URL manipulation.
- Limit to <=500 historical requests, rate limiting, empty history and no draft handling.

**Approval behavior**
- New REVIEW creates one PENDING approval; ALLOW/DENY create none; idempotent retry creates none extra.
- OWNER/ADMIN permitted, VIEWER prohibited from mutations; outsiders cannot read/write.
- Concurrent approve-vs-reject results in one terminal state and one auditable transition; test against **real PostgreSQL** locks.
- Expired, stale, revoked-agent and foreign decision/approval cases cannot be incorrectly approved. Clearly define and test whether agent deactivation blocks approval (secure default: block).
- A decision or approval never causes any refund, network request, or tool execution.
- Historical Build 01 REVIEW decisions are not retroactively made actionable.

**Regression/quality**
- Existing 89-test baseline remains passing and action evaluation API is backward-compatible.
- `php artisan test --compact` passes on isolated SQLite.
- `./scripts/test-postgres.ps1` passes on disposable PostgreSQL, including the new concurrency tests.
- `npm run types:check`, `npm run build`, ESLint, Prettier, Pint, `composer audit` and `npm audit` pass (report any external advisories accurately; do not hide them).
- Expand Playwright: at least one full **Replay Lab** journey and one **Approvals** journey against real HTTP/PostgreSQL; include 390 px mobile assertion and no browser JS errors. Preserve the current secret-handling safeguards.
- Keyboard focus, form labels, loading/empty states, error handling and obvious clickable controls must work. No dead links.
- Never compromise `.env`, `APP_KEY`, `DB_PASSWORD`, existing development workspace data, the project-local Docker service, or existing git history.

## 6. Suggested implementation order (work autonomously)

A. Inspect code and enumerate existing schema/service contracts. Confirm clean Git baseline and branch.  
B. Implement replay candidate construction using the *existing* validator + pure evaluator; add pure tests.  
C. Implement replay persistence, bounded scoped controllers/endpoints and integration tests.  
D. Build Replay Lab list/detail/impact UI and policy-editor `Preview impact`.  
E. Implement transactional approval creation, pending/status transitions, snapshot-manifest freshness and concurrency tests.  
F. Build Approvals list/detail UI; integrate real REVIEW journey.  
G. If P0 is green, implement P1 Usage & Plans.  
H. Run complete SQLite/PostgreSQL/browser/static/security validation; fix issues; inspect desktop/mobile screens.  
I. Update docs and make **one local commit on the feature branch** once checks are green. Never push.

## 7. Documentation and reporting requirements

Update `README.md` and append to `docs/IMPLEMENTATION_LOG.md` (do not erase Build 01 history). Add `docs/BUILD02_TECHNICAL_NOTES.md` covering:

- Replay baseline versus candidate semantics, snapshot persistence and size limits.
- Approval states, 24-hour expiry, stale-policy detection, authorization matrix and lack of external execution.
- Actual web/API routes, database migrations, screenshots path (only non-secret images), and manual walkthrough.
- Existing limitations: no operational security certification, no production deploy, no live payment gateway, no third-party agent integration unless genuinely implemented.
- Exact test commands, **actual** pass/fail counts and any known caveats.

Use a clean local Git commit message such as `Implement ScopeTwin Build 02 replay and approvals` only after validation.

## 8. Final answer format — in Turkish

Return a concise but comprehensive completion report:

1. `BUILD 02 sonucu`: P0 replay, P0 approvals, P1 usage/pricing each DONE/PARTIAL/NOT DONE.
2. `Gerçek uçtan uca kanıt`: exactly what was exercised over live HTTP; include a table of the three demo requests and original/baseline/candidate decisions.
3. `Güvenlik`: org isolation, role checks, replay side-effect test, concurrent approval test and expiry/stale safeguards.
4. `Test sonuçları`: actual PHP tests/assertions (SQLite and PostgreSQL), browser specs, build/typecheck/lint/audits.
5. `Dosyalar ve migration'lar`: concise summary and user-data safety.
6. `Git`: branch, commit SHA, clean/dirty tree; confirm no push or deploy.
7. `Sınırlamalar ve sonraki öneri`: only verified claims, no invented results.

**Start implementing now. Do not stop at a plan, and do not return prematurely when tests fail.**
