# Agent instructions — ScopeTwin

You are building a NEW hackathon project, not modifying an existing user project. Follow docs/PRODUCT_BRIEF.md, docs/ARCHITECTURE.md and docs/ACCEPTANCE_TESTS.md; execute docs/BUILD01_CODEX_PROMPT.md first.

## Operating rules
- Implement real code and test it. Do not stop at plans, screenshots, static prototypes, or fake success messages.
- UI copy in English; final progress summaries to user in Turkish.
- Use Laravel 13 and the official React starter kit (React 19, Inertia, TypeScript, Tailwind, shadcn/ui) if supported by installed environment. PostgreSQL by default; database-backed queue is acceptable initially to minimize dependencies. Prefer framework defaults to custom infrastructure.
- Keep a well-structured **single repository/monolith**; no unnecessary microservices or separate frontend backend deploy targets.
- Scope isolation is non-negotiable. Resolve organization context from the authenticated identity or agent key; never trust a submitted `organization_id` to authorize access.
- Never let an LLM grant privileges or override deterministic authorization. Without matching allow/review rule, **deny by default**. DENY takes precedence over REVIEW, REVIEW over ALLOW.
- Monetary amounts are INTEGER MINOR UNITS (e.g., USD cents), not binary floating point.
- Separate dry-run replay from executed actions. Replay never calls a real external service or triggers irreversible operations.
- Avoid credentials and secrets in code, README, console output, screenshots, seeders and Git commits. Do not overwrite unrelated existing files.
- Use idempotency for operation requests; predictable JSON validation errors; rate limit action API endpoints; log decision identifiers and non-sensitive metadata, not access tokens or PII.
- Write feature tests for positive and negative authorization; no multi-tenant cross-read/write; review/deny/allow and default deny; idempotency; replay side-effect-freedom.
- Avoid excessive integrations, complex role hierarchies, billing providers, unnecessary settings screens, placeholder pages, ornamental gradients, emoji, and artificial 'AI' styling. Prefer a polished, legible enterprise UI with real data and coherent states.
- Maintain `docs/IMPLEMENTATION_LOG.md` with actual changes, commands, test results, blockers, and known limits. Never invent passing results.
- Do not deploy to production, spend money, create third-party accounts, push to GitHub, or make external mutations without explicit user instruction.
- Progress autonomously through the current build instructions unless genuinely blocked; use conservative defaults, test, and document them.
