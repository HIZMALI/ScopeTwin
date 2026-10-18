# ScopeTwin Build 02 technical notes

Implementation date: 2026-10-18 (Europe/Istanbul). Branch: `feat/build-02-replay-approvals`, based on local `main` at `bf3f0eb`. The previously untracked user-supplied Build 02 prompt was preserved unchanged.

## Delivered scope

| Priority | Feature | Result |
| --- | --- | --- |
| P0 | Real historical replay against an unpublished draft | Implemented and verified |
| P0 | Transactional, authorized human approvals | Implemented and verified |
| P0 preferred enhancement | Originating agent's read-only approval lookup | Implemented and verified |
| P1 | Actual usage and proposed pricing presentation | Implemented and verified |

No external action executor exists. Approval is a documented status, not an authorization token to issue a refund. No external payments, live billing, LLM/Backboard calls, deployment or remote push were performed.

## Replay semantics

`ReplayService` uses the same pure `PolicyEvaluator` as live requests, through `ReplayComparison`; there is no second policy engine. Only validated `ConditionSchema` rules and persisted normalized inputs are evaluated.

1. **Original recorded:** immutable historical decision, its original reason and actual published-version reference, if any.
2. **Baseline simulated:** the complete current ACTIVE published policy set, frozen at replay start.
3. **Candidate simulated:** that baseline with the selected policy replaced by its frozen draft; a new policy's draft is added. Other policies are preserved. Disabled policies are absent and a disabled/foreign/non-draft candidate is rejected.

The main metric compares candidate **effect** with baseline effect. Reasons/matched rules are retained even when effects are unchanged. Original-to-baseline differences may come from earlier policy changes and are explicitly explained separately. DENY > REVIEW > ALLOW, default deny, integer minor-unit boundaries and deterministic ties remain unchanged.

The candidate adapter uses `draft:<sha256>` as an in-memory evaluator identifier and stores `source: DRAFT`, the actual source draft UUID and a fingerprint. A draft match always has `policy_version_id: null`; its marker never becomes a fake published database version. The report exposes the frozen definition/fingerprints and deterministic explanations of matching reason codes and precedence, without an LLM.

Every run stores baseline definitions/fingerprint, candidate definition/fingerprint, candidate policy name/ID, creator, filter values, selected action/decision IDs plus input hashes, sample count and transition counts. Each result stores its frozen normalized input and three outcomes, including reasons and matched references. Later draft edits, publications or disabling do not rewrite past reports.

Bounds and behavior:

- Latest 100 historical decisions by default; integer limit 1–500 enforced on the server. All samples are linked to same-organization persisted action requests.
- Optional same-organization agent, exact action name, and inclusive UTC date filters. Empty history creates an honest zero-sample report with a link to agent/API setup.
- Baseline and candidate snapshot metadata are each bounded to 1 MiB. Conditions retain Build 01's finite typed DSL and 1–20-value `in` lists.
- Replay creation: 5/minute/user and 10/minute/organization. Results: 20-row pagination plus exact action, candidate effect and changed/unchanged filters. History: 15-row pagination.
- Organization policy shared lock freezes policy state; every application policy mutation takes its exclusive counterpart. Selection and persistence happen in one transaction. History is append-only through application flows. Relation eager loading avoids per-row SELECTs; a 500-item test asserts fewer than 15 SELECTs for the service path.
- Replay writes only `replay_runs`, `replay_results` and one sanitized `replay.completed` audit. Tests compare complete before/after contents of live requests, decisions, versions, agents, keys and approvals, and assert no HTTP calls.

### Measured HTTP scenario

The Chromium Replay Lab spec created two published refund rules through the real UI, sent three actual authenticated HTTP evaluations and saved a new unpublished DENY draft for USD refunds `>10000`. It clicked Preview impact and measured:

| Request | Minor units | Original | Baseline | Candidate |
| --- | ---: | --- | --- | --- |
| `refund.issue` / USD $120 | 12000 | ALLOW | ALLOW | DENY |
| `refund.issue` / USD $2,400 | 240000 | REVIEW | REVIEW | DENY |
| `unknown.action` | — | DENY / DEFAULT_DENY | DENY / DEFAULT_DENY | DENY / DEFAULT_DENY |

Exactly **2 changed, 2 newly denied**; the unknown action is unchanged. The browser checked unchanged live decision rows and approval counts and confirmed the draft remained unpublished. It also ran no-change and empty-filter reports, then published/edited the candidate and verified the old run/results were identical. A later replay explicitly demonstrated recorded ALLOW / baseline DENY / candidate ALLOW, keeping attribution correct. Non-sensitive measured output is in ignored `storage/build02-http-replay.json`.

## Approval lifecycle and freshness

For a new REVIEW, `EvaluateAction` creates one `PENDING` approval in the same transaction as its action, decision, key last-used update and audits. A unique `policy_decision_id` enforces one approval per decision. An idempotent retry returns the exact Build 01 response and creates no additional approval; an old REVIEW without a manifest stays read-only and returns REEVALUATION_REQUIRED.

Approval metadata contains the originating organization/agent/key/decision IDs, normalized action summary, complete frozen published policy manifest/fingerprint, and creation/expiry time (24 hours). It contains no raw key or resource label. No free-text reviewer note is required or stored.

Persisted lifecycle:

```text
PENDING -> APPROVED | REJECTED | STALE | EXPIRED
```

- Only OWNER/ADMIN in the active identity-derived workspace may resolve. VIEWER can read but cannot create replays or resolve approvals.
- Resolver locks in this order: agent, originating key, organization policy shared lock, approval. Evaluation/revocation uses the same compatible order; policy mutations take the organization exclusive lock before touching policies.
- Exactly one terminal transition succeeds. Any subsequent resolution returns JSON 409 and adds no second transition audit.
- Expiry at `expires_at <= now` blocks mutation and commits EXPIRED. Before expiry, a changed active published set, disabled agent or revoked originating key blocks approval and commits STALE. A new key for the same agent does not revive a request made by a revoked key.
- Fingerprints use canonical key ordering and explicit active published membership. Published version/definition changes, enablement or disablement invalidate the manifest. Changing only an unpublished draft does not.
- Invalid pending states are **derived read-only** in list/detail/status lookups; they appear under Resolved with clear explanations. A subsequent mutation finalizes EXPIRED/STALE transactionally and returns 409 **after commit**, so error handling cannot roll the invalidation back. GET never changes approval state. No cron is needed to prevent invalid approval.
- Rejection/approval never changes the original REVIEW decision. `resolved_by_user_id`, time, finite resolution reason and a sanitized audit are stored. UI confirms the intended decision and explicitly says “Approved in ScopeTwin. No external action was executed.”
- Historical Build 01 REVIEW decisions are not backfilled. Historical reviews tab and decision detail instruct a fresh evaluation using a new idempotency key.

The read-only agent endpoint is confined to the verified key's organization **and agent**, including when two agents share a workspace. Its response contains status/IDs/timestamps and `execution_performed: false`, never the key or a tool execution permission. Revoked credentials return 401.

## Authorization and routes

| Route | Authorization / behavior |
| --- | --- |
| `GET /replays` | Session member, scoped history/drafts |
| `POST /replays` | OWNER/ADMIN, CSRF, replay throttles; redirects to real report |
| `GET /replays/{id}` | Member, scoped frozen report with filters |
| `GET /replays/{id}/results/{resultId}` | Member; both run and result must belong to workspace and each other |
| `GET /approvals?tab=pending\|resolved\|historical` | Member; actual counts and effective validity |
| `GET /approvals/{id}` | Member; safe summary and frozen policy context |
| `POST /approvals/{id}/resolve` | OWNER/ADMIN, CSRF, 30/minute/user/IP, status APPROVED/REJECTED only |
| `GET /api/v1/agent/decisions/{id}/approval` | Verified scoped key; own agent only; read-only; agent throttles |
| `GET /usage` | Member; scoped 30-day actual usage and hypothetical plans |
| `POST /api/v1/agent/actions/evaluate` | Existing Build 01 contract preserved exactly |

Every resource path uses canonical UUID constraints. Foreign UUIDs return 404, restricted role mutation 403, missing session/API authentication 401 (browser GET redirects when appropriate), invalid fields 422, terminal/stale/expired resolution 409, and throttling 429. Request organization IDs cannot authorize anything and are prohibited in mutations. Real-browser tests also confirmed cross-site no-token mutation attempts return **419** while state remains unchanged.

## Database and user-data safety

Additive migration: `database/migrations/2026_10_18_000002_create_replay_and_approval_tables.php`.

- `replay_runs`: UUID, organization/actor/candidate references, frozen manifests/fingerprints, filters, selected sample manifest, summary, completion status/times; organization/time index.
- `replay_results`: UUID, organization/run/historical references, normalized input, three effects/outcomes, changed boolean; unique run/request and organization/run/changed index.
- `approval_requests`: UUID, organization/agent/key/action/decision references, unique decision, frozen action/manifest, state/expiry/resolver/reason; organization/status/expiry and organization/time indexes.

No DML backfill, reset, seed or rollback was run against the user's `scopetwin` database. Only the additive migration was applied there. A hash-only preservation guard captured original rows before implementation and afterward confirmed `.env` and **all 183 original domain rows** were unchanged. This includes original users, memberships, keys, policy versions and historical decisions. Sessions were not reset. Original `.env`, APP_KEY, DB_PASSWORD, Compose service/volume and port 18731 were preserved.

SQLite uses an in-memory database. PostgreSQL regression/concurrency uses `scopetwin_test`. Playwright creates/migrates a separate `scopetwin_browser_test`, starts its own port-18732 server and leaves synthetic fixture history only in that database. No test server disables CSRF; it runs with the normal local web middleware and a distinct session cookie. Only disposable test databases are rolled back/re-migrated. No secrets or raw database records are written by `scripts/verify-preservation.php`; its ignored guard file contains hashes and IDs only.

## Usage and plans

Usage is computed from actual persisted rows for the last 30 UTC days: requests excluding idempotent retries; daily ALLOW/REVIEW/DENY counts; top 50 agents by evaluations; replay and approval creation counts. Queries are workspace-scoped and use existing organization/time indexes. The 30 daily chart bins are real zero-inclusive results, not fabricated traffic.

Free sandbox, Team $79/month and Business $299/month are clearly marked Proposed pricing / Pricing preview — no charges. Boundaries are hypotheses, not enforced paid entitlements. No checkout, subscription or billing integration is presented as live.

## Verification commands and evidence

```powershell
php artisan test --compact
./scripts/test-postgres.ps1
npm run types:check
npm run build
npm run lint:check
npm run format:check
vendor/bin/pint --test
composer validate --strict
composer audit
npm audit
npm run test:browser
php scripts/verify-preservation.php verify
```

Measured results at the implementation checkpoint:

| Check | Actual result |
| --- | --- |
| Original baseline rerun before changes | 89 PHP tests / 537 assertions passed |
| SQLite full suite | **126 tests / 924 assertions passed** |
| PostgreSQL full suite | **126 tests / 924 assertions passed** |
| PostgreSQL migrations | Both domain migrations rolled back/reapplied successfully in disposable test DB |
| Original concurrency | 4 identical requests => one decision; 2 conflicting inputs => 200 + 409 |
| REVIEW concurrency | 4 simultaneous retries => one pending approval |
| Approval concurrency | Simultaneous approve/reject => 200 + 409, one terminal transition audit, original REVIEW unchanged |
| Policy race | Approval started while policy mutation lock held => STALE/409 after changed policy state commits |
| Chromium | **3 specs passed**: existing Build 01, Replay Lab, Approvals; real HTTP/PostgreSQL, 1440/390 px and no JS page errors |
| Build / TypeScript / ESLint / Prettier / Pint | Passed |
| Composer validate / audit | Valid / no security advisories |
| npm audit | 0 known vulnerabilities |
| Existing data / environment | Hash guard passed for `.env` + 183 original rows |

The PHP suite verifies replay substitution/addition, three-way attribution, precedence/money boundaries, immutable replay results, malformed/foreign/disabled candidates, sample limits, empty history, bounded SELECT count, scope/filter attacks, role restrictions, time boundary, policy/key/agent invalidation, creation rollback, terminal conflict, historical handling, no network/live-record side effects and actual usage counts. The existing 89 tests were preserved.

Browser screenshots (ignored; synthetic test data, no visible key):

- `storage/screenshots/build02-replay-desktop.png`
- `storage/screenshots/build02-replay-mobile.png`
- `storage/screenshots/build02-approval-desktop.png`
- `storage/screenshots/build02-approval-mobile.png`

Replay and approval desktop/mobile images were visually inspected in addition to DOM overflow assertions. Tests do not save raw keys/passwords, traces or automatic secret-bearing screenshots. Non-sensitive command logs are ignored under `storage/build02-*.txt`.

## Manual walkthrough

1. Keep the app at `http://127.0.0.1:18731`; register/login normally. Existing data remains intact.
2. Create an agent and save its one-time key. Create/publish USD refund ALLOW `<=50000` and REVIEW `>50000` policies. Send actual $120, $2,400 and unknown-action requests using README/API examples.
3. Save a **new** unpublished DENY `refund.issue`, USD, amount `>10000` policy. Click Preview impact. Inspect changed=2, the matrix, all three outcome columns, individual reasons and draft fingerprint. Do not publish during this comparison.
4. Open Replay Lab history and run a filter for `unknown.action` to see No changes detected. Filter a nonexistent action to see an honest empty sample.
5. Open Approvals. The new $2,400 REVIEW has one PENDING record. Confirm Approve or Reject. Inspect the preserved original REVIEW and query the agent status endpoint; no refund occurs.
6. For a fresh REVIEW, edit an unpublished draft (approval remains valid), then publish/disable an applicable policy (approval becomes stale). A revoked originating key or disabled agent also blocks approval. Old Build 01 REVIEW rows stay in Historical reviews.
7. Open Usage & Plans for actual 30-day counts and explicitly hypothetical prices.

## Remaining limits

- Synchronous, latest-N bounded replay; no background queue, arbitrary request-set upload, long-running batch system or retention/deletion UI. Published snapshot and replay protection are application-level, not database immutability triggers/RLS.
- Approvals are human documentation only. No execution/reconciliation/financial integration exists. Invalid pending states are derived on read and persisted on mutation; there is no expiry cleanup job.
- Existing first-membership workspace model and simple OWNER/ADMIN/VIEWER permissions remain; no invitations/organization switching or external identity integration.
- Browser verification is Chromium. No Firefox/WebKit run, independent penetration test, formal accessibility certification or production load test is claimed.
- Mail delivery, paid entitlements/checkout, external agent integrations, LLM/Backboard and production hosting remain unconfigured. CI files exist but were not executed remotely.
- Future work: operational hardening/retention, broader browser/accessibility tests, real usage limits, and a read-only public judge walkthrough if requested. Deployment, paid services and pushes remain subject to explicit instruction.
