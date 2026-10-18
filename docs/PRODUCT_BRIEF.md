# ScopeTwin — Product brief (v0.1)

## One-liner
ScopeTwin lets teams simulate, deploy, and audit safety policies for AI agent tool actions. The special differentiator is **Policy Replay**: compare the consequences of a proposed policy version on recorded actions before promoting it.

## Customer / pain
Engineering teams running support, CRM and operations agents need to prevent unauthorized tool actions without stopping useful automation. Generic AI agent dashboards often show activity but do not make policy-change consequences easy to test against recorded actions.

## Ideal customer
10–100-person SaaS teams and AI automation consultants integrating agents with business APIs. Buyers: engineering lead / head of operations / security lead.

## Core demo
1. User signs up; an organization is provisioned.
2. User adds a `Support Agent` and receives an API token (shown once).
3. Organization policy set allows customer.read and refund.issue up to USD $500; refund over $500 requires approval; unknown actions denied; data export outside approved domains denied.
4. Real HTTP request to `POST /api/v1/agent/actions/evaluate` returns ALLOW for a $120 refund request, REVIEW for $2,400, DENY for an unknown or prohibited action.
5. Decision log shows inputs sanitized, policy matched, reason code, timestamp, org and agent.
6. User changes a policy draft, runs Replay Lab on previously recorded safe sample traffic. System compares two versions and summarizes which decisions change. No side effects occur during replay.

## Day-one measurable result
Successful, traceable real HTTP policy evaluations. No actual financial refund is executed; any refund connector in the sample environment is purely simulated and must be clearly labeled.

## In scope for MVP
- Registration, login, organization creation, membership role OWNER, protected app shell.
- Agent CRUD + one-time API token generation + hashed token storage.
- Policy CRUD with constrained typed conditions; versioning (draft vs published); deterministic engine.
- Request evaluation with JSON API, decision records, default deny, idempotency.
- Real operation dashboard, decision table/details, functioning policy form.
- Replay Lab with frozen recorded/baseline/candidate decisions from real history (BUILD 02).
- Human documentary approval with authorization, expiry and freshness checks (BUILD 02).
- Actual usage and proposed pricing (BUILD 02); paid entitlements/usage caps remain deferred.
- Stateless read-only public judge tour, landing page and isolated production packaging (BUILD 03); remote launch is not authorized/performed.

## Out of scope initially
Generic conversation chatbot; real payment transfers; an SDK in five languages; SOC 2 certification; autonomous arbitrary tool execution; enterprise SSO; cross-region multicloud; advanced billing gateway; multi-agent workflow builder; mandatory LLM on critical decision path.

## Suggested billing hypothesis (not validated pricing)
Free sandbox; Team $79/month; Business $299/month; compare willingness-to-pay later. Use sandbox/mock billing until real payment provider onboarding is approved, and disclose mock status in submission materials.

## Risks
- Competitors offer generic AI guardrails; differentiate on what-if policy replay and explainability.
- Security claims must be scoped to code verified by tests; do not claim independent audits.
- Partner-credit eligibility requirement may require extra organizer confirmation.
