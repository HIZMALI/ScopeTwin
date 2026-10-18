# ScopeTwin — acceptance and adversarial test contract

A result is accepted only after a command or browser journey truly passes. Do not report anticipated passes as measured.

## Core functional tests
- Authenticated user is owner of a newly provisioned organization after signup.
- New agent creates token once. Token authenticates agent endpoint; raw token is not persisted and cannot be redisplayed.
- `customer.read` matches ALLOW for a properly configured published policy.
- `refund.issue` with `amount_minor:12000, currency:USD` => ALLOW for maximum $500 rule.
- `refund.issue` with `amount_minor:240000, currency:USD` => REVIEW when review-above-$500 rule is published.
- A matching DENY policy takes precedence over REVIEW and ALLOW.
- Unknown action => DENY with reason `DEFAULT_DENY`.
- Draft policy cannot affect a live evaluation; published policy can.
- A request with nonexistent/revoked token => 401; no decision persisted.
- Absent or invalid idempotency key => validation error or documented response.
- Repeat request with same token, same key, same normalized payload => same `decision_id` without duplicate action request.
- Reuse same idempotency key with different payload => 409.
- Malformed negative amount, floating-point minor amount, invalid currency, absurdly large fields => 422.
- Seed fixtures or demo accounts are not generated in production unless explicitly enabled.

## Multi-tenancy attack tests
- User A cannot list/read/update/delete agent, policy, decision or audit record of organization B even when UUID is known.
- Agent API token A may evaluate only for its own organization and agent; no body tenant override.
- Policy snapshot and replay cannot reference a decision from a different organization.
- Organization B cannot revoke keys belonging to A.
- Restricted roles cannot publish policy or create agent keys.

## Replay Lab tests (Build02)
- Same historical event + same policy snapshot yields same deterministic decision.
- Replay of draft against prior events produces a clear before/after decision-change table.
- Replay cannot create real approval requests, business actions, network tool calls or new live decision entries.
- Original decision history remains unchanged when policy versions are updated.

## UI and documentation checks
- Real login and agent creation, policy publish, API evaluation, decision log visible in browser.
- Sidebar links functional; no decorative controls without behavior.
- No mobile overflow at ~390px width; tables scroll or collapse.
- README has setup, architecture, schema summary, working curl/PowerShell demo, known limitations.
- `php artisan test` passes and `npm run build` passes.
- If Playwright is available, run a happy-path browser test; otherwise explicitly report skipped reason.

## Reporting format

BUILD 03 additionally verifies opt-in/idempotent setup refusal on user data, fixed-tenant public projections, zero public GET writes/cookies, credential/foreign-UUID leakage, blocked public mutations, registration/login abuse with spoofed forwarded IPs, explicit trusted-proxy behavior, generic production errors, no false mail delivery, security headers/nonces and separate readiness. Production-mode HTTP/browser tests use a new isolated PostgreSQL service and built assets at 18733, with desktop/mobile, axe and dialog checks. Runtime DB privilege, private file/host/CSRF probes, 10,000-row query/replay scale, modest load and a 13-table backup/restore drill are separate executable checks. Remote TLS/SMTP, external provider integration and independent certification must stay NOT TESTED until actually verified.
At the end of a Codex run, output:
1) implemented behavior, 2) paths changed, 3) test commands and counts/results, 4) demo URLs/credentials if local dev and safe to share, 5) known blockers, 6) proposed next build. Never invent a test count.
