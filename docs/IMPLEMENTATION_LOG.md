# Build 01 implementation log

Implementation date: 2026-10-18 (Europe/Istanbul). Workspace: `C:\Projects\ScopeTwin`.

## Starting state and decisions

- Read `AGENTS.md`, `PRODUCT_BRIEF.md`, `ARCHITECTURE.md`, `ACCEPTANCE_TESTS.md` and `BUILD01_CODEX_PROMPT.md` before implementation. The directory contained only instructions / planning documents; no Git repository or application existed. Original documents were preserved.
- Detected PHP 8.4.24 with PDO PostgreSQL/SQLite, Composer 2.10.2, Node 24.13.1, npm 11.8.0, Docker 29.4.2. PostgreSQL CLI was not on the host PATH; Docker was operational and a PostgreSQL 18 image was available.
- `composer create-project laravel/react-starter-kit .bootstrap --no-interaction --prefer-dist` installed official starter v1.0.1, initially Laravel 12.70.0. Moved only generated files into the current directory after collision checks. Removed the now-empty bootstrap directory.
- Created the previously absent Git repository with `git init -b main`; no additional branch or remote was created. Existing global Git identity was available.
- Upgraded to Laravel 13.37.0 / Tinker 3.0.2 using explicit Composer constraints and `composer update --with-all-dependencies`. Official starter React 19, Inertia 2, TypeScript, Tailwind 4 and shadcn/ui were retained. No fallback framework was needed.
- A dedicated Compose PostgreSQL 18 service runs on loopback port 55439 with an isolated named volume and generated password in ignored `.env`. No other containers were modified.

## Implemented code

- Domain UUID schema: organizations, memberships, agents, scoped HMAC-hashed API keys, policies, immutable versions, normalized action requests, decisions, audit events. Foreign keys, unique indexes and organization/time indexes; reversible migration.
- Transactional registration provisions OWNER membership. First authenticated membership determines browser workspace; no body-selected context. OWNER/ADMIN can manage, VIEWER can inspect. All domain resource reads/writes include workspace scope.
- Typed action normalization, explicit supported currency list, strict JSON integer minor units, lowercase domains, 8 KiB request bound, unknown-field rejection, discarded free-text resource labels.
- Safe condition DSL with field/operator/type whitelist, one expression per field, AND matching, bounded `in` lists. Pure evaluator: DENY > REVIEW > ALLOW, deterministic tie order, default deny.
- Policy creation, draft edits, publication and disable workflows. Publication uses a policy row lock and atomically changes the current snapshot pointer. Old snapshots / decisions remain intact.
- High-entropy agent keys returned once through non-cacheable JSON, never session flash / Inertia props. Key revocation / agent disable checks are repeated under transaction locks.
- Real evaluation endpoint with 401/409/422/429 JSON behavior; transactional request/decision/audit persistence; unique idempotency tuple and per-agent row lock; 60/minute/agent and 180/minute/IP limits.
- English React UI: minimal four-link shell, real dashboard counts and seven-day activity, agent management / example payload, typed policy editor / version history / publication, decision filtering / pagination / detail. Responsive scroll-contained tables and mobile logout. No fake action executor or fabricated activity.
- Explicit `scope:demo-policies` command, restricted to local/testing, idempotent; default seeder creates no demo accounts or fixtures.
- PowerShell setup, secure API demo, PostgreSQL verification scripts; README architecture, schema, examples, limits and pricing hypothesis. CI lint workflow changed to read-only checks.

Primary new/changed paths: `app/Domain/*`, `app/Services/*`, `app/Models/*`, domain controllers and middleware under `app/Http/`, `app/Console/Commands/SeedDemoPolicies.php`, `routes/{web,api}.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, domain migration, `database/seeders/DatabaseSeeder.php`, `resources/js/pages/scope/*`, `resources/js/components/{scope,one-time-key}.tsx`, auth form/layout compatibility updates, `resources/css/{app,scope}.css`, `resources/views/app.blade.php`, `tests/Unit/PolicyEvaluatorTest.php`, `tests/Feature/ScopeTwinTest.php`, `tests/browser/journey.spec.ts`, `tests/scripts/concurrency.php`, `playwright.config.ts`, `scripts/*`, `compose.yaml`, `.env.example`, package manifests / lockfiles, README and this log. The rest of the application originates from the official starter kit.

## Measured verification

| Command / check | Actual result |
| --- | --- |
| `php artisan migrate --no-interaction` | Four migrations applied successfully to local PostgreSQL |
| `php artisan --version` | Laravel Framework 13.37.0 |
| `php artisan test --compact` | **89 passed, 537 assertions**; SQLite isolated suite, 4.46 s on recorded run |
| `./scripts/test-postgres.ps1` | **89 passed, 537 assertions** against PostgreSQL; domain migration rollback/reapply also succeeded |
| `php tests/scripts/concurrency.php` (inside PostgreSQL script) | Four simultaneous identical service requests returned one decision ID; two simultaneous different payloads returned 200 + 409; exactly two total requests, decisions and audit events across the two scenarios |
| `npm run build` | Vite production build passed |
| `npm run types:check` | TypeScript passed with no diagnostics |
| `npm run test:browser` | **1 passed** (18.2 s final recorded run), Chromium happy-path journey using real HTTP and PostgreSQL |
| `npx eslint resources/js` | Passed with no diagnostics |
| `npm run format:check` | All matched files use Prettier code style |
| `composer validate --strict` | composer.json is valid |
| `vendor/bin/pint --test` | Passed |
| `composer audit` | No security vulnerability advisories found |
| `npm audit` after dependency remediation | 0 vulnerabilities |
| `php artisan route:list --except-vendor --path=api` | Actual POST evaluation route registered |
| UI visual review | Viewed actual desktop overview and 390 px decisions screenshots. Browser asserts no page overflow on Overview, Agents, Policies, Decisions, policy editor and agent detail |
| `./scripts/setup.ps1` on existing installation | Passed; exact pre/post APP_KEY and DB_PASSWORD values compared in memory and confirmed unchanged; no secrets printed |

The browser journey registered and logged in with a generated synthetic account, created a Support Agent, held its key only in memory, refreshed to confirm it was not redisplayed, created/published three rules, received live ALLOW ($120), REVIEW ($2,400), DENY (unknown action), and ALLOW (customer.read). It checked replay of an identical idempotency key, conflicting input 409, invalid amount 422, real dashboard count 4, filter result count, snapshot detail, desktop/mobile layout, revocation 401 and mobile logout. No JavaScript page errors were observed. It leaves synthetic policy/decision history in local PostgreSQL; generated passwords are not written to source or output. Key-bearing traces and automatic failure screenshots are disabled.

PHP tests additionally cover registration ownership, token hashing/non-redisplay, exact money boundaries, DENY precedence, unknown-action default deny, draft isolation, immutable prior versions, disabled policies, malformed structures/integers/currencies/domains, missing/invalid/revoked keys, tenant overrides, known foreign UUID attacks against reads/writes/publish/revocation/audit, VIEWER restrictions, ADMIN permissions, missing membership denial, per-agent idempotency scope, pagination, rate limits, production demo-seed restrictions and pure snapshot re-evaluation with no HTTP calls or database writes. The full Replay Lab is not implemented or claimed.

Non-sensitive response IDs from the live HTTP run are reproduced in README. Local generated verification output is ignored under `storage/` and Playwright results are ignored; no API token or password is included in this log.

## Failures found and resolved

- First Composer CLI upgrade lost the caret through Windows `.bat` argument handling and attempted exact 13.0; Composer rejected vulnerable old releases. Explicit JSON constraints corrected this. A subsequent dependency resolution identified Tinker 2's Laravel 12 ceiling; upgraded to Tinker 3. No advisory checks were disabled.
- Initial npm install reported 33 vulnerabilities (3 low, 6 moderate, 22 high, 2 critical). `npm audit fix` resolved compatible dependencies; final audit reported zero.
- Starter TypeScript interfaces needed index signatures for current Inertia form constraints. Fixed three auth forms. Removed unused starter welcome/dashboard placeholders; scope dashboard is the routed implementation.
- First new feature run: 34 failed / 51 passed because Laravel prioritized throttle middleware before custom agent authentication. Moved the agent-identity limiter inside authentication after successful key resolution; retained independent ingress limiting. Suite then passed 85 tests / 474 assertions. Further authorization / pagination / immutability and malformed-UUID tests raised the final total to 89 / 537.
- Default dev port 8000 was unavailable on this Windows host. Started successfully at 18731 and updated setup/examples accordingly.
- Initial browser runs reached the real policy/API workflow but failed the 390 px overflow check. A screen-reader-only absolute table header was outside a positioning container, extending document width despite scroll clipping. Made the table scroll wrapper positioned; mobile assertions and browser journey passed afterward. Added mobile logout and verified it.
- `php artisan route:list --compact` is unsupported on this Laravel version; repeated with supported flags and verified the API route.
- Final review added canonical UUID route constraints to make malformed domain identifiers return 404 before reaching PostgreSQL. Added currency-precision-aware amount display and bounded long-title rendering. Setup preserves existing application/database keys instead of rotating them on reruns.

## Scope and known limits

- No remote push, deployment, paid service, third-party account, real payment/refund, external tool execution, or Backboard integration was performed.
- Build 02 remains: Replay Lab UI/API, authorized approval queue, usage metering and pricing pages. REVIEW records a requirement; it does not create an executable approval.
- First membership is the fixed workspace context. No invitations, organization switching or complex role administration.
- Policy snapshots are protected by application models/services, not a database immutability trigger; direct database administrators are outside this application authorization boundary. Scope enforcement is application-level, not PostgreSQL RLS.
- Serialized writes for a single agent deliberately limit throughput. Real PostgreSQL concurrency was verified; SQLite is for the fast test suite, not equivalent lock behavior.
- Currency support is an explicit 27-code subset. No custom expressions or negative-domain comparator.
- Browser verification used Chromium only. No production load test, independent security audit or formal accessibility certification.
- Local recovery email delivery is unconfigured; array mail driver avoids writing reset secrets to logs. Register a fresh account to use the local app; there are no shared demo credentials.
- npm installation emits deprecation notices for starter-kit transitive `lodash.isequal` and ESLint 9; audits report zero known vulnerabilities. GitHub workflows were updated but were not run remotely.

## Recommended continuation

Build 02 should persist explicit replay runs/results scoped to organization, compare current and draft snapshot sets using the same pure evaluator, prove no live side effects, and add a separately authorized human approval queue. Preserve all original decisions and published snapshots. Keep deployment, paid integrations and remote pushes subject to explicit user instructions.

## Repository handoff

Build 01 sources were saved in a local commit on `main` with message `Implement ScopeTwin Build 01 policy governance monolith` (209 files, including the starter and preserved project instructions). `.env`, database files, screenshots, runtime logs, raw HTTP verification output, dependency directories and generated assets are ignored. A staged-content scan found no literal generated agent keys, application keys or database passwords; its setup-script match was the password-generation code, not a stored credential. `git diff --cached --check` passed. No remote is configured and no push/deploy command was run. The local application was verified at `http://127.0.0.1:18731`; an app browser tab was requested (the UI tool returned `queued`).

---

# Build 02 — Replay Lab, approvals and usage

Date: 2026-10-18 (Europe/Istanbul). User explicitly requested the local feature branch for this build, overriding the usual main-only preference. Created `feat/build-02-replay-approvals` from `bf3f0eb`. Starting tree had one untracked user file, `docs/BUILD02_CODEX_PROMPT.md`; it was read, preserved byte-for-byte and retained for the commit. No unrelated user edits were overwritten.

Read the Build 02 prompt, original instructions, product/architecture/acceptance/Build 02 planning documents, README, previous log, actual services/models/migrations/controllers/routes/UI/tests. Reran the baseline: **89 tests / 537 assertions passed** before implementing changes.

## Delivered implementation

- Additive migration `2026_10_18_000002_create_replay_and_approval_tables.php`: scoped `replay_runs`, `replay_results`, `approval_requests`; UUIDs, foreign keys, unique run/request and decision/approval relationships, organization/time/status/expiry indexes.
- `SnapshotManifest` canonical validation/fingerprints and bounded metadata. `ReplayComparison` adapts a frozen draft for the existing `PolicyEvaluator`, preserving precedence/default deny and emitting explicit draft references without invented published UUIDs.
- `ReplayService`: identity-derived workspace/role, frozen active published baseline plus validated draft, 100-default/500-max historical sample, optional scoped agent/action/UTC date filters, stored sample IDs/input hashes, three outcomes, immutable report snapshots and actual transition counts. Writes only replay rows and sanitized replay audit at domain level; no live operations or approvals are emitted.
- Policy mutations and snapshot captures coordinate on organization row locks. Draft publication is separate from replay; old report definitions/results remain unchanged after edits/publication/disablement.
- `ApprovalService`: exactly one PENDING approval on a new REVIEW, within the evaluation transaction. Originating key, full policy manifest/fingerprint, safe summary and 24-hour expiry are captured. Idempotent retries preserve exact Build 01 API responses and create no duplicate approval, including retries of historical legacy REVIEW records.
- Authorized atomic approve/reject with agent/key/org-policy/approval locks, single terminal transition, finite audit reasons, reviewer/time, 409 conflicts. Expiry, changed published membership/definitions, disabled agent and revoked originating key block approval; draft-only changes do not. Invalid pending status is derived on GET and finalized on mutation, preventing approval without a cron job and keeping lookups read-only.
- Scoped originating-agent read-only status endpoint, with no execution capability and no cross-agent access. Legacy Build 01 reviews are not backfilled and are labelled reevaluation required.
- React UI: Replay Lab history/form, real Preview impact actions, report with three-way outcomes/transition matrix/frozen definitions/filters/row detail/no-change and empty states; Approvals Pending/Resolved/Historical tabs with genuine badges, confirmation dialog, explicit no-execution feedback and safe context; Usage & Plans with actual 30-day counts and honestly proposed prices, no checkout.
- Existing four sections and evaluation API remain functional. New navigation and desktop/mobile layout were verified. Added a read-only `lint:check` npm script.
- Browser tests now own isolated port 18732 and `scopetwin_browser_test`; the user's port 18731 and database are preserved. Added hash-only local data-preservation guard and real PostgreSQL approval concurrency script. Updated README, planning status and `BUILD02_TECHNICAL_NOTES.md`.

## Commands and actual results

| Command / evidence | Measured result |
| --- | --- |
| Initial `php artisan test --compact` | 89 passed / 537 assertions, 4.07 s |
| Final SQLite `php artisan test --compact` | **126 passed / 924 assertions**, 8.12 s recorded run |
| Final `./scripts/test-postgres.ps1` | **126 passed / 924 assertions**, 24.66 s while browser suite also ran |
| Disposable PostgreSQL migration rollback/reapply | Both Build 01 domain and Build 02 migrations succeeded |
| Original concurrency script | 4 identical requests => one saved decision; different payload race => 200 + 409; correct row/audit counts |
| New REVIEW concurrency | 4 simultaneous duplicate REVIEW requests => one decision and one approval |
| New approve/reject concurrency | One 200 + one 409, one terminal transition audit, immutable REVIEW preserved |
| Concurrent policy mutation vs approval | STALE / 409 after changed manifest state committed |
| `npm run test:browser` | **3 passed** (1.1 min): Build 01 regression, full Replay Lab, full Approvals workflows over HTTP/PostgreSQL |
| `npm run types:check` / `npm run build` | Passed; Vite production assets generated |
| ESLint | `npx eslint .` passed; reproducible via new `npm run lint:check` |
| `npm run format:check` / `vendor/bin/pint --test` | Passed |
| `composer validate --strict` | Valid manifest |
| `composer audit` / `npm audit` | No Composer security advisories / 0 npm vulnerabilities |
| `php artisan migrate --pretend` then local `migrate --no-interaction` | Reviewed additive schema; only Build 02 migration applied to app DB, 122.99 ms |
| `php scripts/verify-preservation.php verify` | `.env` and **183 original rows** unchanged in canonical query-output hashes |
| User prompt SHA-256 comparison | `docs/BUILD02_CODEX_PROMPT.md` unchanged |
| Local `GET http://127.0.0.1:18731/up` | Actual HTTP 200 |

New tests preserve all 89 original tests and cover three-way attribution, draft replacement/addition, unchanged unknown default deny, exact minor-unit boundaries, frozen result persistence, full before/after domain-state comparisons, no HTTP side effects, malformed/disabled/foreign drafts, empty history, 100/500 bounds, bounded SELECT counts, filters, per-user and shared-organization replay throttles, owner/admin permissions, VIEWER prohibition, foreign UUID/run/result/approval/sample attacks, per-agent lookup, transactional creation rollback, 24-hour boundary, stale policy/key/agent contexts, legacy retry behavior, terminal conflicts and real usage.

## Live HTTP/browser evidence

The Replay Lab journey registered a synthetic owner with a generated in-memory password, created an agent/key, published actual ALLOW/refund `<=50000` and REVIEW/refund `>50000` rules through UI, and sent three real API requests. A new unpublished DENY/refund `>10000` candidate produced:

| Request | Original | Baseline | Candidate |
| --- | --- | --- | --- |
| USD 12000 minor units ($120) | ALLOW | ALLOW | DENY |
| USD 240000 minor units ($2,400) | REVIEW | REVIEW | DENY |
| unknown.action | DENY / DEFAULT_DENY | DENY / DEFAULT_DENY | DENY / DEFAULT_DENY |

Exactly two changes/two newly denied. Live decisions and approval counts were unchanged, and the draft remained unpublished. The journey checked filtering, row detail, no-change/empty samples, later publication/editing with unchanged earlier reports, recorded-vs-baseline divergence and actual Usage & Plans totals.

The approval journey exercised idempotent REVIEW creation, read-only agent lookup, confirm/cancel/approve/reject, preserved original REVIEW response, draft-only validity, publication-induced staleness, originating-key revocation and resolved/historical tabs. Both new specs verified unauthenticated reads return 401 and cross-site/no-CSRF-token mutation attempts return 419. Tests ran normal local web middleware, not a CSRF-exempt test server. All specs observed zero browser JavaScript page errors. Desktop viewport was 1440 px; new pages and existing core pages were checked at 390 px for document overflow.

Visually inspected actual replay desktop/mobile and approval desktop/mobile images in ignored `storage/screenshots/build02-*.png`. Tokens were hidden before explicit screenshots; traces and automatic screenshots remain off. No plaintext key/password was written to source/output. Measured replay IDs/outcomes are in ignored `storage/build02-http-replay.json`.

## Failures and fixes during this build

- First new feature GET failed because the Vite manifest predated the newly added replay page. Ran the actual frontend build; backend tests then passed. Did not bypass rendering or Vite in tests.
- First browser runs received Inertia 409 asset-version negotiation responses from the HTTP props helper because `X-Inertia-Version` was missing. Fixed the test helper to send the real DOM page version; no application negotiation/security behavior was weakened.
- A filter test inspected the old table before Inertia finished rendering. Replaced the immediate row-count read with Playwright's auto-waiting `toHaveCount(2)` assertion.
- Visual review found duplicate approval-success banners. Consolidated them and made network-error feedback close the confirmation dialog. The full browser suite passed again after this UI change.
- Expanded checks after initial green runs to prove shared-organization replay throttles across multiple admins and to prove an idempotent retry cannot backfill a legacy REVIEW approval. Final counts are 126 / 924 on both databases.

## Preservation, limitations and handoff

- No reset, seed, rollback or destructive test command ran against `scopetwin`. Only the additive migration ran there. Existing APP_KEY/DB_PASSWORD, `.env`, users, policies, keys, decisions, membership, Docker service/volume and original app port are preserved. Test rollback is confined to explicitly named disposable databases.
- No remote push, deployment, paid subscription, external account, real refund/payment, external tool invocation, LLM or Backboard integration occurred. Billing remains proposed pricing only.
- Synchronous replay is capped at 500 and 1 MiB per policy snapshot manifest. No retention/deletion UI or background batch scheduling was added. Invalid pending states are derived on read and finalized on mutation; this intentionally does not require a cleanup cron.
- Snapshot/data immutability and tenant authorization are application-level; direct database administrators, database RLS/triggers, production load, formal security/accessibility certification and Firefox/WebKit coverage are outside verified scope.
- Existing fixed first-membership workspace model and unconfigured mail delivery remain. The old broader roadmap's public landing/judge demo and optional advisory AI adapter are future work, not claimed as delivered.
- Verified changes were saved in one local feature-branch commit, `Implement ScopeTwin Build 02 replay and approvals`. The working tree was confirmed clean and `main` remained at `bf3f0eb`; no remote or push/deploy was used. Full technical notes and manual walkthrough: `docs/BUILD02_TECHNICAL_NOTES.md`.
- Final staging check: 53 changed/added paths. No original Build 01 test was modified. The default Git whitespace check reports only the preserved user prompt's intentional Markdown two-space hard breaks; the staged source check excluding that original input passes. The input's SHA-256 is unchanged. Secret-pattern scan matched only the existing setup script's password-generation code, not a literal generated secret. Runtime `.env`, data guards/logs, screenshots, generated assets and databases are ignored.

# BUILD 03 — 2026-10-18

Started from the verified BUILD 02 tip `e495b78` on the explicitly requested local `feat/build-03-launch` branch. The two user-provided BUILD 03 instruction files were read completely and preserved. No push, remote creation, deployment, purchase, DNS change, account signup or paid API call occurred.

Implemented production security/configuration guards, script-nonce CSP and response protection, explicit proxy/host trust, registration/recovery throttles, honest unavailable mail recovery, stateless landing/read-only judge tour and DB-backed readiness. Demo CLI setup is opt-in/idempotent and refuses user databases/existing non-demo workspaces; all shown artifacts are persisted through the real evaluator, replay and documentary approval services. No executor was added.

Packaged locked production assets and backend with a digest-pinned, non-root FrankenPHP/PHP 8.4.26 image, explicit migration/seed/key safeguards, private PostgreSQL 18 service, persistent volumes and a DML-only runtime role. The migration-owner password is absent from the web process. New scripts reproduce the isolated loopback 18733 rehearsal, runtime/security probes, scale/load checks, safe screenshots, container scanning and disposable backup/restore. Existing development Compose and 18731/55439 remained in service.

Actual failures drove repairs: Inertia navigation to a stateless landing after logout; Apache bearer forwarding in the early package; brand accessible-name mismatch; low-contrast existing text; non-focusable horizontal tables. Container scans exposed Debian/libxml2 findings, so the final server moved to Alpine. The stock FrankenPHP binary's HIGH x/net issue was repaired to v0.60.0 using the matching official builder. PostgreSQL's old Go-based privilege-drop helper was replaced with `su-exec`; fresh initialization and existing disposable data both work. No advisory was suppressed. Windows WebKit does not traverse links with Tab/Alt+Tab here; programmatic skip focus is checked there and real link Tab traversal is covered in Chromium/Firefox.

Actual evidence:

| Command/check | Observed result |
| --- | --- |
| Baseline SQLite | 126 / 924 PASS |
| Final SQLite | **142 tests / 1031 assertions**, 10.18 s PASS |
| Final PostgreSQL regression | **142 tests / 1031 assertions**, 24.10 s PASS |
| Disposable migrations/concurrency | Two old migrations rollback/reapply PASS; identical/conflicting API requests, REVIEW retry, approve/reject and policy-change race PASS |
| Unchanged Chromium regression | **3 specs PASS**, about one minute; no original test weakened |
| Final isolated production browser suite | **6 scenarios PASS**, 58.2 s; Chromium/Firefox/WebKit, 1440/390px, full HTTP workflow and 48 axe page audits with 0 violations in tested scope |
| Runtime/security | Non-root/debug-off/built assets/no embedded .env/high DB privilege absent PASS; unsafe debug and malformed key rejected; private paths404/forgedHost400/CSRF419/demoCookie0 |
| Scale | 10,000 fixture decisions; 500-item replay **7 SELECT / 390.99 ms**, no changes; organization/time index used |
| Modest HTTP load | 40 requests / concurrency4 / **0 errors**, p50 32.70ms / p95 42.81ms / max63.17ms (local, not an SLA) |
| Backup/restore | New disposable target, **13 domain tables counts/hashes identical** |
| Container security | Final app **0 HIGH/CRITICAL** at 18:00:46Z; final DB **0 HIGH/CRITICAL** at 18:01:14Z; Trivy, no CVE ignore/suppression |
| Frontend/PHP checks | TypeScript/build/ESLint/Prettier/Pint PASS; strict Composer valid; Composer advisory0 / npm vulnerability0 |
| Preservation | Both hash guards: **.env + all 183 original domain rows unchanged**; original app /up200, original loopback ports retained |

README, product/architecture/test contracts, full Turkish BUILD03_TECHNICAL_NOTES, deployment/runbook, go-live checklist, English Devpost/video storyboard/SRT and four actual anonymous screenshots are included. Safe output lives in ignored `storage/build03-*`; the sanitized evidence summary is committed. No new migration was necessary, and no DDL/DML/seed/reset/rollback ran on the user's database.

P0 implementation is complete and locally verified. Remote HTTPS/proxy/HSTS/certificate renewal, SMTP delivery, public GitHub/CI, organizer account eligibility/Discord/credits, actual MP4/upload and Devpost submission remain external gates. AI/Backboard is deferred and no real call was claimed. Direct DB owners remain privileged; RLS/immutable DB triggers, independent penetration test/certifications, enforced billing/quotas and production capacity/RPO/RTO guarantees are not delivered. Full paths/evidence/limitations are in BUILD03_TECHNICAL_NOTES.md and GO_LIVE_CHECKLIST.md.

# BUILD 03.5 — 2026-10-18

Started at `643497d` on the explicitly requested new local `feat/build-03-5-backboard-advisor` branch. Read the complete BUILD03_5_BACKBOARD_CODEX_PROMPT and official Backboard send-message, thread lifecycle, model discovery and billing contracts. No provider key or charged-call authorization was supplied. No live provider request, model/price/balance assertion, purchase, partner redemption, push, remote deployment or BUILD 04A work occurred.

Implemented the optional session/CSRF/OWNER-ADMIN scoped Replay Advisor POST, rigid server aggregate allowlist, bounded one-shot HTTP/JSON adapter, disabled/missing config fallback, escaped React explanation UI, deterministic numeric facts, finite status/timing logging and best-effort thread deletion. New additive advisor tables reserve shared quotas with short user/organization locks before HTTP: maximum three attempts/organization/UTC day and one/user/rolling minute. Frozen fingerprint/model/prompt caching blocks duplicate requests within a lease, including ambiguous failures; no automatic retries. Added an explicit two-flag synthetic-only manual live command, never run with charge confirmation.

Actual failures and fixes: initial missing APP_KEY and URL assumptions caused 72 SQLite failures; test-process-only random key/fixed URL root removes local secret dependency without changing .env. PostgreSQL exposed the new migration ordering bug, fixed by placing it after the original two domain migrations. An external APP_ENV=local override caused CSRF failures in a preliminary PG run; the final disposable test process uses testing, with separate real production-mode CSRF checks. ESLint found a missing node:process import, fixed. Minute-boundary coverage drove the stricter rolling user cooldown. No original authorization/evaluator assertion was weakened or skipped.

Fresh final PHP evidence: `php artisan test --compact` SQLite **169 tests / 1291 assertions**, 8.51s; `./scripts/test-postgres.ps1` **169 / 1291**, 30.95s. Final scope-before-input review added two negative assertions proving malformed bodies cannot change foreign404/viewer403 precedence. All three additive domain migrations rollback/reapply in `scopetwin_test`. Original idempotency/review/approval/policy races PASS. New six-process advisor races: identical => one generation; distinct admins => three generations + three quota rejections; one user/distinct => one generation + five rejections. HTTP is mocked and stray requests prohibited in all PHP tests.

Browser evidence: original three Chromium journeys retained, one added Advisor scenario; **four PASS** including disabled fallback, CSRF, explicit mocked loading/success/escaped text/quota and stable core facts. Isolated built production on new **18734** / `scopetwin-build035` runs **six release scenarios PASS** across Chromium, Firefox and WebKit with desktop/mobile, real API and axe checks. Mocked browser fixtures are not live Backboard evidence. Mobile advisor screenshot was visually inspected. Existing 18733 BUILD03 /up remains 200. Runtime is production/debug false/non-root UID33, migration-owner secret absent and runtime role has no superuser/createDB/createRole privilege.

Types/Vite build/ESLint/Prettier/Pint PASS; strict Composer valid; Composer advisory0 and npm vulnerability0. Security HTTP: private paths404, forged Host400, forged CSRF419, HttpOnly/Lax sessions, public demo cookie0. Final isolated load at19:35:19Z: 40 requests/concurrency4/error0, p50 27.88ms/p95 38.16ms/max61.01ms. Build035 Trivy app19:34:54Z/database19:35:06Z HIGH/CRITICAL0 with no ignore. Fresh database image initialization PASS. Separate backup/restore: **15 table counts/hashes identical**. Secret-pattern301 source paths and Git whitespace checks PASS; no independent security audit is claimed.

Preservation: new hash-only guards compare unchanged .env and **183 existing PostgreSQL domain records**. The current .env at task start selects production/SQLite without APP_KEY or domain schema; it was not rewritten. Existing SQLite domain record count is zero. No migrations/resets/seed/rollbacks/writes ran against the user's application database. Test credentials existed only in process memory; fixture keys/passwords were never printed. Fresh production rehearsal uses separate private secrets/images/volumes/database/18734; existing 18733 and user services were not replaced.

Updated README, architecture, Devpost, go-live and new BACKBOARD_INTEGRATION plus Turkish BUILD03_5_BACKBOARD_REPORT. Key/model/provider setup remains private/operator-owned. LIVE_NOT_TESTED/MODEL_NOT_SELECTED are explicit. The inactive Galuxium partner-promotion issue remains unresolved; ordinary initial credit is not eligibility proof. Provider default-assistant context, operational-log retention, cleanup ambiguity/orphan threads, timeout billing, lease expiry, call quotas not dollar caps, cache TTL not erasure, strict lexical output rejection, SQLite contention and existing WebKit/TLS/SMTP/remote gates are documented. Full command and file inventory is in the Turkish report. Changes are committed locally only after the final checks.
