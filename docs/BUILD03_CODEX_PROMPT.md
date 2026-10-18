# ScopeTwin — BUILD 03: Launch Readiness, Security, Judge Experience & Submission Assets

**Execution mode:** Autonomous implementation inside the existing local Windows repository, subject to the explicit external-action gates below.
**Date:** 2026-10-18 (Europe/Istanbul)
**Target:** A production-like, reproducible, safe and judge-ready SaaS release candidate. This build is **not** permission to push, purchase infrastructure, modify DNS, expose local ports to the Internet or deploy remotely.
**Language:** Application UI and submission materials in polished natural **English**. Final engineering report to the user in **Turkish**.

## 0. Read this first: actual verified project baseline

Working directory: `C:\Projects\ScopeTwin`

BUILD 01: local `main` commit `bf3f0eb`. BUILD 02: branch `feat/build-02-replay-approvals`, local commit `e495b78`, clean as reported. Before acting, inspect actual Git and source; if hashes differ, report rather than resetting anything.

Existing product: Laravel 13.37 + React 19 + Inertia 2 + TypeScript + Tailwind 4 + shadcn/ui + PostgreSQL 18. Login/registration, first-membership workspace scope, OWNER/ADMIN/VIEWER, agents and one-time scoped API keys, deterministic policy DSL and immutable published snapshots, ALLOW/REVIEW/DENY evaluation endpoint with transactional idempotency, real dashboard/decisions, Replay Lab (original/baseline/candidate), transactional human approvals with freshness and concurrency controls, usage and proposed plans.

Key constraints from BUILD 02:
- Current main local browser app `http://127.0.0.1:18731`; its DB is `scopetwin`, PostgreSQL Compose host port `127.0.0.1:55439`. Do not disrupt it.
- Disposable tests use `scopetwin_test` and `scopetwin_browser_test` (browser server port 18732). Preserve and respect those boundaries.
- User data-preservation guard exists: `php scripts/verify-preservation.php verify`; prior baseline included `.env` and 183 original domain rows unchanged. Count may have grown since; use the guard appropriately instead of assuming old count remains current.
- BUILD 02 baseline checks: **126 PHP tests / 924 assertions** on SQLite and PostgreSQL; **3 Playwright Chromium specs**; frontend type/build/lint/format, Pint, Composer validation/audit, npm audit passed. These are historical reported facts, not a claim that this BUILD 03 run has passed.
- One original and live endpoint contract must remain unchanged: `POST /api/v1/agent/actions/evaluate`, returning `decision_id`, `decision`, `reason_code`, `matched_policy_version_id`, and `approval_required` with the existing HTTP/idempotency semantics.
- `APP_KEY`, `.env`, DB password, existing keys, organization and all persisted decisions/versions must remain intact. Never run migrate:fresh, reset, destructive seed, production rollback or APP_KEY regeneration on existing app DB.
- Replay is deterministic, synchronous, bounded to 500 history items, has no execution side effects. Approving a REVIEW is documentary only; **no refund or external action is ever executed**. Neither LLM nor any third party may authorize an action.
- Read and keep aligned: `AGENTS.md`, `README.md`, `docs/PRODUCT_BRIEF.md`, `docs/ARCHITECTURE.md`, `docs/ACCEPTANCE_TESTS.md`, `docs/BUILD02_TECHNICAL_NOTES.md`, `docs/IMPLEMENTATION_LOG.md`, and the actual routes/services/tests.

## 1. Working protocol and external-action safety

1. Inspect git status, current branch, commits, dependencies, tests, and relevant code first. Do not overwrite unexpected user edits. If the tree is clean and expected BUILD 02 commit is present, create **local** `feat/build-03-launch` from the BUILD 02 tip. If not, preserve everything and adapt safely; never hard-reset/rebase/delete user work.
2. Preserve the user's local app and database in operation. Only additive, reviewed migrations on the application database; make all destructive lifecycle/retention commands dry-run by default and disabled on real data unless specifically approved.
3. Make a preservation snapshot using safe, local, non-secret hash and counts. Never copy secrets or sensitive rows into logs, screenshots, git, or tickets.
4. Implement, test and document in this run. Do not stop at planning/scaffolding. Resolve defects, retest and commit verified changes locally, using an accurate commit message. Do not push or create a remote automatically.
5. Absolutely **no external account creation, cloud purchase, domain/DNS edits, GitHub remote/repository creation, push, live deployment, transmission of sensitive data or paid API invocation** without explicit user authorization and usable credentials. Build all deploy assets and execute production-like smoke checks locally. If the remote production deployment is blocked by credentials/approval, write the exact steps and label it NOT DEPLOYED instead of claiming success.
6. Do not add fabricated usage, fake tests, fake billing, a fake AI request, demo policies that mutate user workspaces, or claims of certifications. Any synthetic data must be labeled and confined to an isolated demo/test environment.
7. Prefer shipping an excellent, small and real feature over a large set of incomplete features. Enforce the order P0 -> P1 -> P2. If time/resources are limited, fully finish P0 and clearly mark later items deferred. No arbitrary dependency upgrades unless required for security or compatibility.

## 2. Mandatory P0 scope

### P0-A: Production readiness and operational guardrails

- Review current configuration for `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, logging redaction, CSRF, proxy/host trust, session cookies (`Secure`, `HttpOnly`, sensible `SameSite`), password-reset/email behavior, error pages, response caching of credentials, CORS if applicable, and correct rate-limiting behind a trusted reverse proxy. Add secure defaults without weakening local tests.
- Verify public web server **only serves the `public/` directory**. Never expose `.env`, `.git`, storage internals, raw API credentials, stack traces, test artifacts or debug panels. Add minimal useful security headers compatible with actual Inertia/Vite assets; evaluate CSP carefully rather than adding an untested policy that breaks the app. Enable HSTS only when real HTTPS is enforced.
- Review API-auth and browser-auth boundaries; prove user-provided organization IDs and forged UUID routes cannot bypass scoping. Preserve OWNER/ADMIN/VIEWER and denial of mutation by VIEWER, login throttles, registrations and agent key one-time display. Review forwarded-IP spoofing and sensitive-response cache controls.
- Add a clearly documented operational policy for 24-hour approval expiry, audit/replay data retention, backup and restoration. Do not automatically delete existing historical evidence. No `truncate`, `fresh`, `reset` or `rollback` against `scopetwin`.
- Add a meaningful readiness check (database connectivity and required infrastructure if practical) separately from liveness, while keeping existing `/up` compatibility; avoid leaking versions, credentials or internals to anonymous callers. Ensure scheduler/queue management only if actually needed; do not run unnecessary processes.
- If password recovery cannot send mail (`MAIL_MAILER=array`), explicitly block/mark it unavailable in a public deployment until SMTP is configured, or implement a safe documented alternative. Do not falsely show a successful delivery. User registration must be abuse/rate-limited and safe for public availability.
- Audit direct database immutability limitations and document them; if a carefully tested PostgreSQL trigger improves published snapshot evidence without damaging migrations/SQLite tests, it can be considered, but **not at the cost of existing compatibility**.

### P0-B: Reproducible production-like packaging and smoke tests

- Choose one simple, proven release approach and document WHY (e.g., a production Docker build using PHP >=8.3, Nginx or supported Laravel web server, bundled Vite assets, PostgreSQL; optional Redis only if actually needed). Keep existing Windows local compose/setup working as before. No unrelated containers or projects touched.
- Produce production-specific Dockerfile/config and an example Compose/deployment setup **without hardcoded credentials**; multi-stage frontend build and optimized composer dependencies, non-root runtime where practical, persistent storage, volumes, startup migration safeguards, health checks and least-privilege DB access. PostgreSQL/Redis must not be publicly reachable.
- Bind demo/production services only to safe addresses/ports unless explicitly launching behind HTTPS. Clearly separate local test, production-like rehearsal and real production databases.
- Generate a sanitized `.env.production.example` with placeholders only; protect `.env` and never print secret values. Preserve current `.env` byte-for-byte.
- Add practical `docs/DEPLOYMENT.md` and `docs/OPERATIONS_RUNBOOK.md`: DNS/TLS prerequisites, preflight, backup, forward-only migrations, deploy, rollback strategy (app version vs schema), restart, verification, logs, incident handling, SSL renewal and safe restoration. Provide PowerShell-compatible instructions for Windows host and Linux server steps when applicable.
- Start a **locally isolated production-mode rehearsal** against an explicitly new disposable database/service/port, using built assets rather than Vite dev server. Execute real HTTP health, login/registration (test-only), API ALLOW/REVIEW/DENY, replay, approvals and usage smoke tests. Never reuse/copy a production secret or touch the main user data.
- If Docker production smoke cannot be run here, state exactly which dependency blocks it; still validate syntax/builds and provide executable scripts. Never claim an external production URL exists.

### P0-C: An excellent public judge experience, no authentication friction

A core hackathon requirement is a live SaaS URL accessible to judges. Prepare the product to satisfy it **without exposing tenant data or agent keys**:

- Design a distinctive, professional English public landing page: compelling and accurate headline, concrete 3-step product explanation, screenshot/workflow links, trust/architecture section, who pays/why, clear navigation to Sign in/Create workspace, proposed pricing clearly marked non-billable, and a **Try read-only demo** entry.
- Create a secure, genuinely **read-only judge walkthrough** at e.g. `/demo` and/or `/demo/replay`. It must display **real stored/synthetic demo-workspace artifacts**, not manually hardcoded fake decisions, with explicit `Synthetic demo data` indication. Provide a real 3-way replay (recorded/baseline/candidate), an explainable changed-count table, and a sample `REVIEW` approval status. Where feasible, also show API request/response examples with no real bearer token. Never let anonymous visitors perform policy publication, approvals, write actions, token issuance or disclose any key.
- Isolate demo data in a distinct, tightly scoped demo tenant in a **disposable/demo database** or separately designated non-user workspace, only when explicit `DEMO_ENABLED` configuration is true. Supply an **opt-in idempotent demo setup command** that refuses production/user databases unless explicit safeguards are satisfied and never modifies existing tenants, keys, decisions, or policies. If demo setup is not configured, the page must display an honest unavailable state instead of fake metrics.
- The demo router/controller must whitelist read-only GET operations, scope strictly to the designated demo workspace, cap query sizes, avoid PII and free-text injection, and never expose any non-demo records. A public GET must never change data. Prevent unauthenticated access to ordinary tenant APIs or administrative routes. Test public scraping and unknown/foreign UUID behavior.
- Modernize only the key screens needed for the demo/video: landing, dashboard, policy editor, Replay Lab report, approvals, usage/pricing, mobile nav. Use an understated enterprise visual identity; coherent spacing/type, meaningful empty/loading/error states, accessible labels/focus, no emojis, no contrived AI visuals, no dense marketing claims. Keep one clear function per screen, avoid superficial redesign of working components.
- Include a concise in-product safety explanation: evaluation/approval never execute real tool actions. Do not let users mistake simulated risk reduction or changing decision labels for prevented monetary losses.
- If a safe public demo requires external deployment to verify, implement locally and label external verification pending authorization.

### P0-D: Security, accessibility, reliability and regression suite

- Re-run the unchanged **126 / 924** BUILD 02 regression on SQLite and PostgreSQL (actual final count may increase), existing PostgreSQL concurrency script and all three Playwright specs; do not delete, downgrade or skip tests just to produce green reports.
- Add automated tests for demo route isolation, public GET side effects, leakage of tenant data/credentials, blocked mutations, abuse throttling, registration, production mode/no-debug, security headers, preserved idempotency, replay immutability, stale/expired approval, and wrong-agent approval lookup.
- Browser tests on desktop 1440px and mobile 390px for key pages, also keyboard tab/focus and accessible dialog basics. Run Firefox/WebKit if available and stable, otherwise document exact noncoverage. A basic accessibility audit (e.g., axe if installation feasible) is preferred; report actual violations, not a certification.
- Evaluate DB behavior/query plans at realistic synthetic scale and a modest load smoke on isolated data; measure actual latencies and error rates rather than inventing performance claims. Do not run stress tests against the user's app DB or external systems.
- Validate production build/typecheck/lint/format/Pint, `composer validate --strict`, `composer audit`, `npm audit`, route checks, migration safety, secret scans and `git diff --check`. Fix actual regressions, preserve `APP_KEY`/existing records. Verify `php scripts/verify-preservation.php verify` or equivalent while accounting for intentional **additive** records in a safe way.
- Keep a release evidence table with commands, counts, timestamps, environments, tested URLs, and anything not tested. Security advisories showing zero does NOT equal a penetration test or SOC 2/GDPR certification.

### P0-E: Documentation and public submission package

- Update root `README.md` for public GitHub presentation: product value, accurate features, limitations, actual images where useful, architecture diagram, component ownership/data model, local setup, deployment, API example, security model, reproducible tests, truthful demo workflow, pricing **hypotheses**, license/IP notes (check repo licenses before making claims).
- Author `docs/DEVPOST_SUBMISSION.md` with an **English ready-to-paste** overview covering problem, solution, what's new, how built, main technical difficulties, target market, business model, reproducible evidence, limitations and a concise judge walkthrough. Use `[LIVE_DEMO_URL]`, `[PUBLIC_GITHUB_URL]`, `[VIDEO_URL]` placeholders until real URLs exist. No fictional revenue, customers, savings, compliance certification, AI calls, or deployed infrastructure.
- Author `docs/DEMO_VIDEO_SCRIPT.md` with a 3:00–3:30 min English narrated/silent-captioned actual-product storyboard. Highest-impact demonstration order: 0–20s problem, 20–60s live policy ALLOW/REVIEW/DENY API, 60–135s three-way Replay with **two changed / two new DENY on synthetic $120/$2,400 example**, 135–165s safe human approval without execution, 165–195s architecture/security/pricing/CTA. Use measured screen states only; video output and captions if available must match actual verified functionality. No fake mouse animations that imply unperformed actions.
- Author `docs/GO_LIVE_CHECKLIST.md` and `docs/BUILD03_TECHNICAL_NOTES.md`, with explicit PASS/FAIL/NOT TESTED and exact blockers. Add a public demo readme/walkthrough and a real sequence of screenshots captured only from a test/demo account, **never while API credentials are visible**.
- Optional after P0: Generate ready-to-edit real screenshots, thumbnail and MP4 screencast if local recording/editing tools are available. Otherwise deliver storyboard, SRT draft, capture commands, and honest status. Do not claim a video was made if there is no playable MP4.

## 3. P1 optional: genuinely useful, safely bounded AI explanations (after P0 only)

The core is a **deterministic agent policy system**, which must remain the security source of truth. For stronger product differentiation, optionally add a clearly labeled `AI Explain` / `Policy Advisor` for a *read-only* explanation of an already-computed replay result. A small, reliable feature is enough.

- Backboard's documented messaging interface exists at `https://docs.backboard.io/concepts/messages`. Do NOT assume an API key or paid quota is available. Never hardcode an organization promo code or access secret.
- Implement an adapter behind an **off-by-default feature flag** and env-based secret only if a real provider is configured. Use bounded network timeouts, SSRF-safe endpoint selection (static allowlist), limited sanitized inputs (policy operators/effects and synthetic demo labels, no email, key, IP, free text, tenant IDs, financial personal data), safe output encoding, rate limits and budget limits. Disable persistence/memory unless there's a justified safe reason.
- LLM output can offer narrative suggestions but cannot change policies, decide ALLOW/REVIEW/DENY, approve actions, execute tools or write back into the immutable replay report. Explain uncertainty; never claim invented numeric effects or recommendations are automatically safe.
- If credentials are missing, show an honest `Not configured` state, provide a deterministic explanation from the existing engine, and document external integration NOT VERIFIED. Feature must fail closed/independently without impacting any core flow.
- Add mocked provider tests, contract tests for sanitization/timeouts/authorization, and a genuine live provider smoke test **only** if authorized provider credentials are already supplied and their use is permitted. Never fake an AI response as a live call.

## 4. P2 only if P0/P1 are green

- Refine monetization and quota documentation using real persisted metering; no live billing, checkout, tax calculations, merchant account setup or customer charges without explicit request.
- CI workflow for GitHub Actions including SQLite, PostgreSQL and frontend tests with secret-free fixtures; local `act` or static workflow validation if available. Do not claim GitHub-hosted CI passed before pushing to a remote.
- Optional explicit demo recording pipeline/scripts and previewable thumbnail. Accessibility/performance polishing with measured changes. Avoid adding unrelated subsystems such as SSO, complex org switching, email marketing, external real tool execution, full SOC 2 controls or generalized workflow engines.

## 5. Release gates — the acceptance criteria

BUILD 03 **P0 done** only if all of the following have actual evidence:

1. All previously working BUILD 01/02 API semantics remain; all old PHP/browser tests still pass without being weakened.
2. A production-like **local** built-artifact deployment boots on an isolated test database and passes a real HTTP smoke journey.
3. Existing user DB, `APP_KEY`, `.env`, original agent secrets/hashes, decisions, approvals, replay runs and published policy versions remain untouched except explicitly necessary safe additive migrations (document all intentional changes).
4. A public-friendly **read-only judge tour** shows authentic persisted synthetic replay results and never leaks a non-demo tenant or mutates state.
5. Production configuration demonstrates debug off, secret discipline, proper server document root, appropriate cookie and reverse proxy setup, response protection and rate limiting; no known critical/high unaddressed dependency advisories (report exceptions precisely).
6. Clear API/tenant isolation, replay/approval freshness, concurrency and preservation tests continue to pass on PostgreSQL and Chromium. Desktop and mobile flows have no severe overflow or unhandled JS errors.
7. Deployment/runbook, public README, Devpost draft, video script, demo setup and launch checklist are complete and accurate.
8. If no remote deploy or video can be completed without new account/permission, list them as explicit external launch blockers. **Never label the release LIVE/DEPLOYED or the video COMPLETED unless independently verified with URL/file checks.**
9. Do not create or change a remote, do not push, do not deploy, do not spend money and do not paste any secrets into final logs.

## 6. Suggested commands to run (adapt to actual project scripts)

```powershell
# Inspect, protect existing workspace and verify baseline first
git status --short --branch
git log -3 --oneline
php artisan test --compact
./scripts/test-postgres.ps1
npm run test:browser

# During and after BUILD 03, keep full verification green
npm run types:check
npm run build
npm run lint:check
npm run format:check
vendor/bin/pint --test
composer validate --strict
composer audit
npm audit
php scripts/verify-preservation.php verify
# Run the NEW production-like smoke/asset/deployment checks implemented by this build.
# Use disposable databases and non-conflicting ports only.
```

Also inspect Docker/build tooling before depending on it. In particular, `docker compose down -v` is destructive: never run it on any existing user Compose project/volume. Do not run `php artisan migrate:fresh`, `db:wipe`, `db:seed` on `scopetwin`.

## 7. Final response format (in Turkish, with real evidence only)

Return the following sections, concise but complete:

1. **BUILD 03 status**: P0-A through P0-E each DONE / PARTIAL / BLOCKED, P1/P2 separate.
2. **Actual features and paths**: routes, deployment files, demo route, pages, scripts, docs, migrations, security choices.
3. **Actual test results**: SQLite/PgSQL PHP tests/assertions, Playwright browsers/specs, concurrency, isolated production-mode HTTP, UI/a11y, audits and build.
4. **Preservation evidence**: whether `.env`, APP_KEY, DB, original historical domain rows and ports are unchanged; mention any safe additive change.
5. **Demo evidence**: actual demo URL (local URL only if remote not deployed), source of real/synthetic persisted events, click path, and ability to test without secrets.
6. **External release gates**: hosting/domain/GitHub remote/Backboard credentials/Discord credits/demo video/Devpost submission; explicitly distinguish completed versus requiring user action.
7. **Known risks and defects**: do not hide outstanding limitations or claim certifications.
8. **Git handoff**: branch, commit, status, whether any push/deploy occurred (expected NO).
9. **Next suggested launch instruction**: precise actions, not broad vague recommendations.

**Begin implementation now. Do not stop after analysis. Preserve the working local project.**
