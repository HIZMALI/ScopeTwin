# ScopeTwin — BUILD 03.5 / Backboard AI Replay Advisor

Date: 2026-10-18. This is a narrow, optional integration between the verified BUILD 03 release candidate and BUILD 04A. It must not delay the actual hackathon deliverables.

## Existing repository and non-negotiable baseline

- Repository: `C:\Projects\ScopeTwin` (Windows PowerShell 7).
- Verified BUILD 03 baseline: local `feat/build-03-launch`, commit `643497d`.
- Preserve existing Laravel 13 / React 19 / Inertia / TypeScript / PostgreSQL implementation, demo isolation, `ALLOW` / `REVIEW` / `DENY` deterministic decision engine, immutable historical replay, non-executing approvals and production package.
- Last reported baseline: 142 PHP tests / 1031 assertions for SQLite and PostgreSQL, 3 Chromium regression scenarios, 6 three-browser release scenarios. Independently rerun and record fresh results, rather than claiming these are new results.
- Create a **local** branch `feat/build-03-5-backboard-advisor` from `643497d` after checking `git status`. Do not reset, cherry-pick earlier unrelated projects, merge to main, push, deploy, upload, send messages, buy plans or change DNS.
- Preserve `.env`, `APP_KEY`, `DB_PASSWORD`, Compose volumes, port 18731, development data and existing demo port 18733. Tests must use only disposable environments. Do not store API keys or credentials in source, test fixtures, HTTP logs, reports, screenshots, browser storage, Inertia props, Git or Codex output.

## Objective

Add **ScopeTwin AI Replay Advisor**, an explicitly optional, low-cost Backboard-powered English-language interpretation of *already computed* historical Replay Lab outcomes. It must produce only explanatory text and human review suggestions. **It has no authority to create, edit, publish or evaluate policies; approve/reject reviews; execute tools; issue refunds; or alter any deterministic facts.** If Backboard is disabled, errors, rate-limits or exhausts credit, all ScopeTwin core functionality must continue unaffected.

This is not the same as the Galuxium promotional credit redemption. The organizer's restricted Galuxium partner promotion showed “This promo code is no longer active” in the participant's Backboard account. The ordinary $5 new-account credit does not establish hackathon promo compliance. Do not include restricted promotion strings in tracked files, public docs or commits, and do not falsely claim redemption or eligibility.

## P0 — Backend integration

1. Study current actual code, data model, protected replay routes, UI, Build 03 notes, and test harness before coding. Do not duplicate the policy engine.
2. Use Laravel's server-side HTTP client against the documented Backboard API: `POST https://app.backboard.io/api/threads/messages` with `X-API-Key` header. Reference documentation:
   - https://docs.backboard.io/quickstart
   - https://docs.backboard.io/api-reference/threads/send-message
   - https://docs.backboard.io/concepts/threads
   - https://docs.backboard.io/concepts/billing
3. Config in a separate `config/backboard.php`, sourced from private environment variables: `BACKBOARD_ENABLED=false` default, `BACKBOARD_API_KEY` (server-only), `BACKBOARD_LLM_PROVIDER`, `BACKBOARD_MODEL_NAME`, and conservative timeout, daily and per-user quota fields. Add **placeholder variable names only** to `.env.production.example`/README. Never invent actual credentials and never modify the user's existing `.env` unless explicitly asked. No call happens when disabled or when the key/model is missing.
4. Use a **user-initiated**, role-authorized, CSRF-protected POST route such as `POST /replays/{replay}/advice`. Restrict requests to OWNER/ADMIN in the identity-derived workspace. VIEWER can see existing deterministic replay information but cannot trigger a paid third-party request. Unknown/cross-organization replay IDs return 404. Anonymous public judge `/demo` and `/demo/replay` remain strict read-only GET/HEAD, with ZERO Backboard calls, no secret values and no write operations.
5. Create a rigid allowlisted, server-derived **summary only**, e.g. `sample_count`, effect transition counts, `changed`, `newly_denied`, `newly_reviewed`, `unchanged` and an explicit label that the scenario is simulated. Do not transmit UUIDs, agent keys, user emails, tenant names, request/event identifiers, raw event inputs, customer records, free text, IP addresses, audit logs, sample IDs, exact domains, currency/amount data, policy source definitions or arbitrary user content. Whitelist and cap the serialized payload. No browser-supplied content reaches the provider.
6. Send the chosen summary to a short safety-focused `system_prompt` which asks for (a) a two- or three-sentence English explanation and (b) up to two suggested questions for a human reviewer. Explicitly prohibit invented causal claims, prevented-loss claims, legal/compliance guarantees, authorization recommendations, or instructions to execute transactions. Prefer `json_output=true` if compatible with the selected model; explicitly send `memory: "off"`, `web_search: "off"`, no tools, `stream: false`, and no unneeded features. Avoid persistent conversation reuse.
7. IMPORTANT: Backboard creates assistant/thread records even for a one-shot message. Where supported, delete the generated ephemeral thread after extracting the result with `DELETE /threads/{thread_id}`, in a safe cleanup path. Do not promise that deletion erases provider operational logs. If cleanup fails, record only a sanitized warning without IDs, request bodies, tokens or model output. No provider call in public GET or automated tests.
8. Validate model output structure and length (e.g. max 900 visible chars). Render it as escaped plain text, never untrusted HTML. Keep numeric facts/transition matrix rendered **only from the deterministic ReplayService**. Provide a clear label: “AI-generated explanation (Backboard), not a policy decision”. For unavailable/error/budget-exhausted cases, show a truthful disabled/error state and a deterministic non-AI explanation; no fake successful analysis.
9. Enforce small, safe, configurable **server-side call quotas**, initially max 3 new provider calls per organization per UTC day and max 1 new call per user per minute, with concurrency-safe counters. A short-lived cache keyed on frozen replay contents/fingerprint + model + prompt version may avoid duplicate API charges. Do not describe quotas as a dollar cap or billing guarantee. No automatic retries for ambiguous provider timeouts. Handle 401/402/429/5xx/timeouts without affecting live decisions, publishing or approval state. Log only non-sensitive status classes and timings. Document actual balance monitoring through the Backboard dashboard.
10. Select a genuinely available and affordable text model only after checking available provider/model names and indicative unit prices from official docs/dashboard; do not guess a model identifier, cost per call, or remaining credit. Keep the feature disabled until provider/model/key are configured privately. No third-party requests from backend unit tests.

## P1 — UI and judge narrative

- On authenticated Replay Lab report view, provide an unobtrusive **Explain with AI** action, small loading/error state, clear AI badge/attribution, and deterministic side-by-side numeric facts always visible. Show no action when disabled or show an honest disabled state; no automatic generation on navigation.
- Do not turn ScopeTwin into a chatbot. Do not change primary workflow or business logic.
- The public judge tour's current frozen three-outcome comparison must still work with provider disabled. Optional illustration of the feature in Devpost/video must be based on a verified real API response and labelled appropriately, not a fabricated claim. The product must still function after the initial $5 promotional credit expires (normally after 30 days).
- Update `README.md`, `docs/ARCHITECTURE.md`, `docs/DEVPOST_SUBMISSION.md`, `docs/GO_LIVE_CHECKLIST.md` and a new concise `docs/BACKBOARD_INTEGRATION.md` with truthful integration status, exact setup steps, privacy limits and cost controls.

## Acceptance tests (real, no bypasses)

- Regression: preserve original PHP tests across SQLite and PostgreSQL and all existing browser/release tests.
- With provider disabled or missing key, no outgoing HTTP to Backboard and no breaking UI.
- Mocked success: one valid AI explanation visible while stored replay facts, baseline/candidate and published policies remain byte-identical; no changed approvals or decisions.
- Mocked 401, 402, 429, 500, timeout, malformed JSON, excessively long output: graceful failure/fallback and zero changes to core data.
- Cross-tenant read/mutation, VIEWER restrictions, missing CSRF, anonymous/public demo write or paid call all blocked.
- Multiple identical clicks and concurrent requests respect caching and shared organization quotas without duplicate provider charges when achievable; document any residual limitation honestly.
- Prove no key/identifiers/private input appear in browser HTML, console, screenshots or logs. No raw secrets may be included in the test evidence.
- Run SQLite, PostgreSQL, concurrency, TypeScript, Vite build, ESLint, Prettier, Pint, composer/npm audits and Playwright release/regression journeys. Report actual counts/results.

## Optional, explicitly authorized live smoke (not part of standard automated tests)

After the account owner creates a Backboard key from `Settings → API Keys`, privately sets it in local ignored environment (never sends it through chat, Codex instructions, screenshots or Git), and chooses an actually available economical text model, perform **one** user-authorized low-cost live API smoke via a separately named manual command/flag such as `--live-backboard-smoke --confirm-charge-credits`. Use only synthetic aggregate replay statistics. Verify actual provider response, clean up any generated thread, and report SUCCESS/FAIL without raw content, key, billing secrets or thread IDs. Do not spend credits by default, auto-enable in production, buy credits or turn on auto-recharge. If not configured, report LIVE_NOT_TESTED instead of calling the integration complete in production.

## Delivery

1. Commit all verified code on the new local branch, preserving `643497d` as parent; do not push/deploy.
2. Deliver `docs/BUILD03_5_BACKBOARD_REPORT.md` with exact changes, fresh evidence, environment variables needed (names only), any known privacy/cost/cleanup limitations, and the exact operator procedure for configuring the key privately.
3. Continue to BUILD 04A **only after** this feature is verified; prioritize the real 2–5-minute demo video, public GitHub and HTTPS deployment over further feature expansion.
4. Final report in Turkish. State clearly whether a live provider call actually succeeded and whether the Galuxium partner-code issue remains unresolved.
