# ScopeTwin — architecture contract (v0.1)

## Stack
Laravel 13 with first-party React + Inertia TypeScript starter, PostgreSQL, Vite, Tailwind + shadcn/ui. Feature tests with Pest/PHPUnit; use Playwright for critical browser journey if available. Use database-backed queue first; defer Redis until needed.

## Boundaries
- Browser UI authenticated with normal Laravel session auth; all UI data is authorized by membership + role.
- Agent HTTP API authenticated with random high-entropy scoped token, stored ONLY as a keyed prefix and SHA-256 hash; never store or log plaintext after issuance. Support token revocation.
- `OrganizationContext` is resolved by authenticated membership or token; never from request body.
- `PolicyEvaluator` is a pure deterministic function over a validated normalized ActionRequest + a published policy snapshot; it has no database writes or external HTTP calls. A separate service persists the decision transactionally.
- `ReplayService` calls the SAME evaluator against snapshot versions but persists only optional replay result metadata, never executes business tools.
- An optional `AIExplanationProvider` adapter may use Backboard for advisory summaries later; unavailable API must not break core product. LLM output never controls ALLOW/REVIEW/DENY.

## Schema — logical (names may adapt to starter)
- organizations: id uuid, name, slug, created_at
- organization_memberships: organization_id, user_id, role (OWNER|ADMIN|VIEWER), unique org/user
- agents: id uuid, organization_id, name, status, created_at
- agent_api_keys: id uuid, organization_id, agent_id, prefix, key_hash, revoked_at, last_used_at
- policies: id uuid, organization_id, name, status (ACTIVE|DISABLED), created_at
- policy_versions: id uuid, policy_id, organization_id, version INTEGER, state (DRAFT|PUBLISHED), conditions JSON, effect (ALLOW|REVIEW|DENY), reason_code, created_at, published_at
- action_requests: id uuid, organization_id, agent_id, idempotency_key, action_name, amount_minor nullable, currency nullable, destination_domain nullable, normalized_request_json, created_at. Unique `(organization_id, agent_id, idempotency_key)`.
- policy_decisions: id uuid, organization_id, agent_id, action_request_id, policy_version_id nullable, decision (ALLOW|REVIEW|DENY), reason_code, evaluated_at
- audit_events: id uuid, organization_id, actor_type, actor_id nullable, event_type, subject_id, metadata_json (NON SENSITIVE), occurred_at
- In later builds: approval_requests, policy_replay_runs/results, usage_counters, subscription_plans.

## Typed action input
Allowed fields: action_name (known string, <=128), resource_type optional, amount_minor optional nonnegative integer <= reasonable max, currency ISO code optional, destination_domain optional validated DNS-like hostname, resource_label optional. Reject unknown unsafe object structures in critical fields. No dynamic JavaScript eval, `eval()`, regex-from-tenant, SQL snippets, or arbitrary policy expressions.

## Policy conditions DSL
Define a safe typed finite policy condition schema, e.g.:
```
{
  "action_name": {"eq": "refund.issue"},
  "amount_minor": {"lte": 50000},
  "currency": {"eq": "USD"}
}
```
Supported comparators: `eq`, `in`, `gt`, `gte`, `lt`, `lte` where appropriate by field type. Conditions across fields are AND. Multiple values for same field are not necessary in Build01. Reject invalid operators and types. Match on normalized input.

Policy precedence: If any matching PUBLISHED active DENY: DENY; else if matching REVIEW: REVIEW; else if matching ALLOW: ALLOW; else DENY (DEFAULT_DENY). A DENY rule cannot be trumped by an ALLOW. For deterministic tie-break within the same effect, order by policy ID/version consistently. Drafts do not affect live requests. Publish atomically and preserve old versions.

## API contract
`POST /api/v1/agent/actions/evaluate`
Headers: `Authorization: Bearer <token>` and `Idempotency-Key: <opaque-id>`
Body:
```
{
  "action_name": "refund.issue",
  "amount_minor": 240000,
  "currency": "USD",
  "resource_type": "refund"
}
```
Response HTTP 200 on successful evaluation, including DENY (business decision, not transport authentication failure):
```
{
  "decision_id": "uuid",
  "decision": "REVIEW",
  "reason_code": "REFUND_REQUIRES_APPROVAL",
  "matched_policy_version_id": "uuid",
  "approval_required": true
}
```
Authentication failure uses 401; malformed input 422; forbidden tenant membership 403/404; throttling 429. An identical key + identical input returns same saved result, without new side effect. Key reused with different input returns 409 conflict. Enforce a DB unique constraint and safe transaction under concurrent duplicate requests. If unusual conflict races remain, document and test them.

## Browser pages (Build01)
- Auth: login/register from official starter, provision first organization.
- Dashboard: REAL count cards and recent decisions, not dummy figures.
- Agents: list/create/detail/revoke key, show secret once.
- Policies: list/create/edit/publish with typed fields and explainers.
- Decision log: real records with filtering or pagination.
Keep navigation minimal; non-built sections should not appear as clickable dead links.

## Data security
- Enforce organization policy and scoped queries in every controller/service, including UUID route binding.
- Credentials redacted on logs and never present in Inertia props after initial token creation.
- Ensure rate limiting, auth, CSRF on browser forms, input validation, audit records for important changes.
- Never call external refund or payout APIs in the hackathon demo. No real PII in fixture datasets.
- No claim of formal security certifications or production maturity without verification.

## Operational quality
- `.env.example` with safe placeholders and documented local setup; no secret `.env` commits.
- Setup script or clear README for Windows PowerShell, Docker Compose optional for local PostgreSQL.
- Seed demo scenarios only in development/explicit demo environments.
- Unique indexes on key multi-tenant tables; migrations reversible; indexes on created_at for decision log queries.
