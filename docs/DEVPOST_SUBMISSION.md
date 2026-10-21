# ScopeTwin

**Tagline:** Know what your agent policy changes before it goes live.

- **Website:** https://scopetwin-hizmali.onrender.com
- **Read-only demo:** https://scopetwin-hizmali.onrender.com/demo
- **Replay Lab:** https://scopetwin-hizmali.onrender.com/demo/replay
- **Source:** https://github.com/HIZMALI/ScopeTwin
- **Video:** https://youtu.be/6cyv4wC1CR4

Prepared submission text. Public website/source endpoints and video metadata were checked on 21 October 2026. Devpost submission and account-specific eligibility remain the owner's responsibility; this document is not proof of submission or acceptance.

## Inspiration

Support and operations agents request actions with business consequences: looking up customers, issuing refunds or exporting data. Teams need explicit authorization boundaries, but a policy edit can affect legitimate requests in ways that are difficult to see from the rule alone. A log explains what happened; teams also need evidence of what would change.

## What it does

ScopeTwin evaluates typed requests against versioned deterministic policies and returns ALLOW, REVIEW or DENY. Unmatched requests are denied; DENY takes precedence over REVIEW and ALLOW. Agent identity fixes the workspace, and integer minor units keep monetary comparisons exact.

The three-way Replay Lab compares the Original recorded decision, a frozen published Baseline and an unpublished Candidate. It freezes inputs and definitions so a later policy edit cannot rewrite the comparison. Human review records approval or rejection with policy, credential and 24-hour freshness checks. Approval preserves the original REVIEW and does not execute an action.

In the synthetic example, a $120 refund is ALLOW, a $2,400 refund is REVIEW and an unknown action is DENY. A candidate denying USD refunds above $100 produces **2 changed / 2 newly denied**. These are measured policy-label changes, not claimed savings or prevented losses.

## How we built it

A single Laravel 13 monolith with React 19, Inertia, TypeScript, Tailwind/shadcn and PostgreSQL 18. The official Laravel React starter supplied authentication and UI foundations. The project adds the policy evaluator, scoped services, frozen replay, approval lifecycle, audit evidence and Docker release package. Development used AI-assisted coding tools; framework and dependency attribution is preserved in [third-party notices](THIRD_PARTY_NOTICES.md).

The backend uses bounded typed input, identity-derived organization context, one-time hashed agent credentials, transactional idempotency and immutable published snapshots at the application boundary. A non-root FrankenPHP/Caddy image serves the built application. The database runtime account has DML access without schema ownership. The public tour reads bounded persisted synthetic artifacts without accounts, bearer credentials or mutation controls.

## Challenges

An idempotent retry must return the original decision even after rules change. Replay must separate recorded history from a current baseline and a candidate. Human review must become invalid if the published policy context or originating credential changes. Snapshot capture, transactions and database locking keep those boundaries explicit; PostgreSQL tests exercise concurrency separately from fast SQLite tests.

Actual deployment also exposed Linux page-path case sensitivity and proxy-header trust issues. External browser and spoofed-header checks verified the fixes rather than relying only on local builds. The final application passed 212 SQLite tests with 1,615 assertions and three live PostgreSQL-backed Chromium scenarios. See [dated evidence and limitations](TESTING.md).

## Accomplishments

The running product connects registration, scoped agents, policy publication, real HTTP evaluation, decision history, frozen replay, human review and actual usage counts. The public HTTPS demo lets a reviewer inspect the synthetic scenario without credentials. The 3:50 video uses real application footage, English narration and synchronized captions, without a fabricated AI response.

## What we learned

A replay report needs preserved inputs and definitions, not just a before/after count. Documentary approval must remain separate from execution permission. Deployment-specific proxy behavior also needs external verification: a correct local test cannot establish the actual public HTTPS or rate-limit boundary.

## Business model

Potential customers are SaaS engineering teams and AI automation consultants who need understandable boundaries and traceable policy-change review. The pricing hypothesis is a free sandbox, Team at $79/month and Business at $299/month. These prices and paid entitlements are not validated or active. There is no checkout, revenue or customer-count claim.

## Current limitations

ScopeTwin does not execute refunds, run external tools or process payments. Backboard AI Replay Advisor is optional and **disabled in production**; no successful live LLM explanation is claimed. The product and video work independently of it. Real API cost and sponsor-credit eligibility are unverified.

Replay is synchronous and bounded to 500 requests. Tenant isolation and evidence immutability are application controls; database administrators remain privileged. Email delivery and password recovery are disabled. No independent security certification or commercial SLA is claimed. The free Render preview may sleep, and its PostgreSQL service is scheduled to expire on 19 November 2026 without managed backups/PITR.

## Judge walkthrough

1. Open the [read-only demo](https://scopetwin-hizmali.onrender.com/demo) and confirm the synthetic-data label.
2. Inspect the stored ALLOW/REVIEW/DENY requests and credential-free API example.
3. Open [Replay Lab](https://scopetwin-hizmali.onrender.com/demo/replay): three requests, **2 changed / 2 newly denied**, Original/Baseline/Candidate and frozen fingerprints.
4. Inspect the documentary APPROVED status: original REVIEW preserved, execution performed false.
5. Watch the [real workspace workflow](https://youtu.be/6cyv4wC1CR4) or follow the [repository setup and tests](https://github.com/HIZMALI/ScopeTwin).

## Owner checks before submission

Confirm registration/team eligibility, work dates, required community membership and any partner-credit requirements against the [official event](https://galuxium-nexus-v2-29411.devpost.com/) and [rules](https://galuxium-nexus-v2-29411.devpost.com/rules). Account-specific redemption/eligibility is not established by this repository. Never include restricted promo strings or credentials. Complete the [submission checklist](SUBMISSION_CHECKLIST.md) in the owner's account; no Devpost submission is performed by maintaining this file.
