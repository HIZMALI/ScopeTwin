# BUILD 01 — Implement now, do not stop at planning

This is an execution prompt for Codex. Work in the current brand-new repository and implement an actual working product slice. Read `AGENTS.md`, `PRODUCT_BRIEF.md`, `ARCHITECTURE.md`, and `ACCEPTANCE_TESTS.md` before acting.

## Priority P0 — framework and data
1. Inspect current directory and local runtime (PHP, Composer, Node, PostgreSQL/Docker). Do not damage other repositories or existing files. If the directory is empty except these documents, bootstrap a NEW Laravel 13 application using the official React starter kit in this directory or a temporary child directory and move into place cleanly. If current runtime cannot support Laravel 13, diagnose and document; choose the smallest compatible stable alternative only if truly necessary.
2. Set up PostgreSQL for local development where feasible, migrations, auth, organizations and organization memberships. New registration creates its own organization with OWNER access; authenticated pages use active membership context.
3. Add scoped domain models and migrations per architecture: agents + hashed scoped agent keys, policies + published/draft versions, action_requests, decisions, audit_events. UUIDs where appropriate. Generate migrations and feature tests.

## Priority P1 — real policy workflow
4. Implement deterministic PolicyEvaluator with typed JSON operator whitelist, matching AND across fields, DENY>REVIEW>ALLOW, default DENY, deterministic rule tie-break. Make it pure and directly unit-testable; no LLM on evaluation path.
5. Implement create/edit/publish policies. Publishing creates immutable version snapshot. Do not mutate old published versions. Add read/write authorization.
6. Implement `POST /api/v1/agent/actions/evaluate` using high-entropy bearer keys, SHA-256 keyed hashes, strict validation, rate limiting, idempotent persistence with unique constraint, safe collision detection, and decision/audit history. The endpoint is decision-only, not an actual refund executor.
7. Seed **development-only** demo policies for: allow customer.read; allow refund.issue <= 50000 USD minor units; review refund.issue >50000 USD; default deny unknown actions. Include a blocked export rule where safe and clearly documented.

## Priority P2 — high-quality actual UI
8. Build a professional English enterprise app shell. Minimal left navigation: Overview, Agents, Policies, Decisions. Use React+Inertia+TypeScript+Tailwind+shadcn. No emoji, gratuitous gradients, randomly colored cards, giant hero banners, generic AI illustrations or non-functional buttons. Ensure responsive layout, loading/empty/error states, meaningful accessible forms.
9. Overview counts from actual DB. Agents screen creates an agent and one-time key; provides the sample API payload to use. Policies screen allows creating/publishing valid rules. Decisions shows live database-backed records with decision pill and timestamp. Make the API demo easy to reproduce.

## Priority P3 — finish and verify
10. Add unit, feature and, if feasible, browser tests against acceptance cases. Validate migration and routes. Run `php artisan test`, `npm run build`, and TypeScript checks where supported. Fix test failures.
11. Write README.md with architecture diagram (ASCII/Mermaid), setup commands and exact HTTP request/response examples (using the real API), mock-vs-real feature disclosures, pricing hypothesis, security model and limitations.
12. Create `docs/IMPLEMENTATION_LOG.md` with executed commands, real outcomes, test counts, unresolved issues; save an informative Git commit if local Git is configured, without remote push.

## Exit criteria — must be demonstrable
- User registers/logs in and sees scoped organization dashboard.
- Can create an agent and a policy; publish rule.
- Sends actual HTTP evaluate request with token and receives saved ALLOW/REVIEW/DENY result.
- Sees request decision and policy reason in a working browser page.
- Two-organization isolation test passes.
- At least critical automated tests + frontend build pass.

Do not stop after architecture/plans. If hard-blocked by missing local tooling, complete as much code and verification as possible; document exact blocker. Do not claim to have deployed, pushed, or integrated Backboard if not performed. Final response IN TURKISH: work done, exact passing tests, paths, known limitations, next steps.
