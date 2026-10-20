# ScopeTwin

**Tagline:** Know what your agent policy changes before it goes live.

**Live demo:** [LIVE_DEMO_URL]

**Source:** [PUBLIC_GITHUB_URL]

**Video:** [VIDEO_URL]

Submission status: prepared locally, **not submitted**. Remote links remain placeholders until an authorized deployment/publication is independently checked. Do not paste a localhost URL as a public demo URL.

## The problem

Support and operations agents increasingly request actions with business consequences: looking up customers, issuing refunds or exporting data. Teams need explicit authorization boundaries. A policy edit can affect legitimate requests in ways that are difficult to see from the rule alone. A log explains what happened; teams also need evidence of what would change.

## What ScopeTwin does

ScopeTwin evaluates typed agent requests against versioned deterministic policies and returns ALLOW, REVIEW or DENY. Unmatched requests are denied; DENY takes precedence over REVIEW and ALLOW. Agent identity fixes the workspace, and integer minor units keep monetary comparisons exact.

The differentiator is a three-way Replay Lab: recorded historical decision, simulated current published baseline, and simulated unpublished candidate. The report freezes the inputs and definitions so a later policy edit cannot rewrite the comparison. Human approvals document a decision with policy, credential and 24-hour freshness checks. They never execute a refund or tool action.

## What's new

The product joins live policy evaluation, frozen what-if evidence and freshness-bound documentary review in one small workflow. The judge tour removes account friction while reading actual persisted synthetic artifacts from an isolated workspace. It exposes no bearer credentials or public mutation controls.

In the reproducible synthetic scenario, a $120 refund is ALLOW, a $2,400 refund is REVIEW and an unknown action is DENY. A candidate denying USD refunds above $100 changes exactly two baseline labels to DENY. This is an observed policy comparison, **not a claim of savings or prevented losses**.

## How we built it

One Laravel 13 monolith with React 19, Inertia, TypeScript, Tailwind/shadcn and PostgreSQL 18. The official Laravel React starter supplied authentication/UI foundations; our project implements the policy DSL/evaluator, tenant-scoped services, snapshot manifests, replay, approval workflow and release package. There are no microservices or required LLM calls.

The backend uses bounded typed input, identity-derived organization context, one-time hashed agent credentials, transactional idempotency, per-agent and organization locks, immutable published snapshots and audit metadata. Docker builds locked frontend/backend artifacts and serves only public/ with a non-root FrankenPHP/Caddy process. The runtime DB role cannot create schemas or act as a superuser. Production settings include debug off, nonce-based CSP, explicit proxy/host trust, protected responses, session/CSRF boundaries and abuse throttles.

## Technical challenges

An idempotent retry must return the original result even after rules change. Replay must compare an honest current baseline with the candidate while keeping recorded history separate. A review must become invalid if policy or originating credentials change during a concurrent resolution attempt. Our implementation uses explicit snapshot capture and compatible database locking, then verifies these cases against PostgreSQL rather than relying only on SQLite behavior.

Launch work also surfaced issues invisible in a source-only build: Apache bearer forwarding, Inertia navigation to a stateless landing page, low text contrast and non-focusable scrolling tables. Real production-mode HTTP and browser checks drove those fixes. The technical evidence includes both fixed failures and remaining container/remote release gates; see BUILD03_TECHNICAL_NOTES.

## Market and business model

Initial buyers are engineering leads at 10–100-person SaaS teams and AI automation consultants. They pay for understandable operational boundaries, faster policy-change review and traceable evidence across agents. The pricing hypothesis is a free sandbox, Team at $79/month and Business at $299/month. These prices and paid entitlements are **not validated or active**. There is no checkout, revenue, customer-count or willingness-to-pay claim.

## What works and what is limited

BUILD 03.6 integrity note: **One live Backboard attempt was made; no accepted AI explanation is claimed.** The source adapter requires a dedicated explicit assistant and has no default-assistant fallback. On2026-10-20 the owner authorized one synthetic attempt in isolated18734. The smoke exited unsuccessfully, the response was not accepted, and thread cleanup was confirmed. The feature was immediately returned to disabled mode. No retry was made. Actual billed cost/free-credit consumption and sponsor-credit eligibility remain unverified. The video therefore shows the working deterministic product and no fabricated AI result.

The real app supports registration/login, scoped agents and keys, policy drafts/publication, real HTTP evaluations, decision history, frozen replay, documentary human review, own-agent approval lookup and actual usage counts. Automated regression runs on SQLite and PostgreSQL; isolated production-mode browser and security results are maintained in the release evidence document.

There is no external action execution, payment service or live billing. The optional Backboard AI Replay Advisor adapter has aggregate-only input, role/CSRF checks, quotas and cleanup. Mocked tests pass; the single authorized real attempt did not produce an accepted explanation. Provider response quality and a working live advisory integration are not claimed. The flag remains disabled, and the public tour works independently. Replay is synchronous and bounded to500items. Scope and immutability are application-level; DB administrators remain privileged. Local verification does not establish public TLS, email delivery, independent penetration-test results, compliance certification or real-world capacity. A verified 3:50 real-browser MP4, English offline narration and synchronized captions are included in the local delivery package. The owner will upload the video and submit Devpost; public links remain placeholders until actual publication is checked.

## Judge walkthrough

1. Open **[LIVE_DEMO_URL]/demo** without an account. Confirm “Synthetic demo data.”
2. Inspect the actual stored ALLOW/REVIEW/DENY scenario and credential-free API example.
3. Compare recorded / baseline / candidate: 3 requests, **2 changed, 2 newly denied**. Expand the frozen fingerprints.
4. Inspect the documentary APPROVED status: original REVIEW preserved, execution performed false.
5. Explore the real workspace flow shown in the video and the documented test commands in **[PUBLIC_GITHUB_URL]**.

## Organizer checks before pasting

Partner-credit issue remains unresolved: the participant reported the restricted Galuxium promotion as inactive in their own Backboard account. Ordinary initial credit does not establish redemption or eligibility. No successful redemption or live AI result is claimed. Confirm with the organizer privately; never publish restricted promotion strings.

The owner must confirm registration/team eligibility, original-work dates, Discord membership and partner-credit redemption in their own accounts. The organizer's [credit announcement](https://galuxium-nexus-v2-29411.devpost.com/updates/45828-kindly-redeem-the-credits-on-mandatory-basis-to-be-eligible-for-judging) calls redemption mandatory; it does not by itself establish an API integration requirement. Confirm interpretation with the organizer. Never include a promo code/API secret in this text. [Official rules](https://galuxium-nexus-v2-29411.devpost.com/rules) were reviewed on 2026-10-18; account-specific eligibility and submission acceptance remain unverified.
