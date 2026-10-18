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
