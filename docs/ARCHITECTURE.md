# Architecture

ScopeTwin is one Laravel 13 application with React 19/Inertia pages and PostgreSQL persistence. Vite builds the frontend into the same deployment artifact. The Docker image uses non-root FrankenPHP/PHP 8.4 with Caddy serving only `public/`. Evaluation, replay and approval workflows are synchronous; no separate worker, Redis or microservice is required.

## Request boundaries

| Surface               | Identity and behavior                                                                                |
| --------------------- | ---------------------------------------------------------------------------------------------------- |
| Authenticated browser | Laravel session and CSRF; workspace resolved from membership; OWNER/ADMIN mutations and VIEWER reads |
| Agent API             | Revocable high-entropy bearer key; workspace and agent resolved from the key                         |
| Public tour           | Stateless GET/HEAD projections from one fixed synthetic workspace; no session or mutation controls   |
| Optional advisor      | Authenticated OWNER/ADMIN POST on a frozen replay; server-only credentials; disabled in production   |

No submitted `organization_id` authorizes access. Resource queries include workspace scope; foreign UUIDs cannot read or mutate another workspace's records. The current browser context uses the first membership; workspace switching/invitations are not implemented.

## Evaluation and policy versions

[`PolicyEvaluator`](../app/Domain/PolicyEvaluator.php) is a pure function over normalized input and a policy snapshot. [`ActionSchema`](../app/Domain/ActionSchema.php) and [`ConditionSchema`](../app/Domain/ConditionSchema.php) define finite fields, types and operators; there are no executable tenant expressions.

Conditions use AND across fields. Supported comparators are `eq`, `in`, `gt`, `gte`, `lt` and `lte` where valid for the field. A matching DENY takes precedence over REVIEW, then ALLOW; no match yields `DENY / DEFAULT_DENY`. Equal-effect matches have deterministic ordering. Live evaluation uses only active published versions; saving a draft has no live effect.

```json
{
    "action_name": { "eq": "refund.issue" },
    "amount_minor": { "lte": 50000 },
    "currency": { "eq": "USD" }
}
```

The evaluation service locks and rechecks relevant agent/key state, applies the evaluator, and transactionally saves normalized input, decision, audit metadata and any REVIEW approval. A unique organization/agent/idempotency-key tuple prevents duplicate requests. Identical retries return the original saved response even after a policy change; changed payloads return 409.

## Data model

The authoritative schema is in [versioned migrations](../database/migrations).

| Tables                                      | Responsibility                                                              |
| ------------------------------------------- | --------------------------------------------------------------------------- |
| `organizations`, `organization_memberships` | Workspace identity, membership and roles                                    |
| `agents`, `agent_api_keys`                  | Scoped agent identity, keyed credential hash, revocation                    |
| `policies`, `policy_versions`               | Current published pointer and preserved draft/published definitions         |
| `action_requests`, `policy_decisions`       | Normalized input, idempotency and recorded deterministic outcomes           |
| `replay_runs`, `replay_results`             | Frozen policy/input manifests and Original/Baseline/Candidate evidence      |
| `approval_requests`                         | Pending/terminal documentary review, expiry, reviewer and freshness context |
| `audit_events`                              | Scoped finite events with non-sensitive metadata                            |
| `advisor_explanations`, `advisor_quotas`    | Optional validated advice, cache reservations and call counters             |

Domain identifiers are UUIDs. Foreign keys, unique constraints and organization/time indexes support consistency and scoped queries. Laravel tables additionally hold users, sessions, cache and queue infrastructure. Amounts use integer minor units. Raw agent keys are returned once and are not stored in session flash or Inertia props; their stored HMAC hashes depend on the stable `APP_KEY`.

## Replay and human review

Replay captures the current published baseline, an unpublished candidate and selected historical inputs. It compares three separate facts: Original recorded decision, simulated Baseline and simulated Candidate. Frozen manifests and fingerprints prevent later edits from changing an existing report. The same evaluator is reused. Only replay/evidence rows and safe audit metadata are written; no new live decisions, approvals or external actions are produced by replay. Default sample size is 100; maximum 500; manifests are bounded to 1 MiB.

A new REVIEW evaluation creates one documentary approval. Pending reviews expire after 24 hours; policy changes, agent disablement or revocation of the originating key invalidate them. GET derives expiry/staleness without writes; resolution rechecks under locks and rejects invalid/terminal state with 409. Approval/rejection never changes the original REVIEW or issues an execution credential. No scheduler is needed to enforce freshness.

## Optional AI boundary

[`ReplayAdvisor`](../app/Services/ReplayAdvisor.php) can explain frozen aggregate transitions after a deliberate authenticated click. It cannot evaluate policy, approve requests or write deterministic facts. Inputs exclude raw actions, identifiers, policy definitions and customer content. Explicit assistant/provider/model binding, call quotas, bounded JSON validation and best-effort thread deletion are documented in [Backboard integration](BACKBOARD_INTEGRATION.md). Production remains disabled; successful live LLM advice is unverified.

## Deployment and security boundary

Production separates migration ownership from a DML-only runtime account. Startup validates/caches configuration and assets; it does not migrate, seed or generate keys. `/up` is liveness; `/ready` checks migrated database/session/cache infrastructure. Debug-off errors, nonce CSP, secure cookies, rate limits and explicit proxy/host trust protect the deployed surface. See [deployment](DEPLOYMENT.md) for the verified Render proxy configuration and [operations](OPERATIONS_RUNBOOK.md) for backups and recovery.

Scope and historical immutability are application controls, not PostgreSQL RLS, WORM storage or cryptographic attestation. Database owners remain privileged. Tests and observed live checks are scoped evidence, not an independent penetration test or compliance certification. See [testing](TESTING.md).
