# ScopeTwin — BUILD 03.6: Safe Backboard Connectivity Fix / Zero-Generation Preflight

**Date:** 2026-10-18  
**Repository:** `C:\Projects\ScopeTwin`  
**Verified starting commit:** `fea6281` on `feat/build-03-5-backboard-advisor`  
**Goal:** Remove blockers to the **first separately authorized live Backboard AI request** WITHOUT making that request in this build.

## Current measured facts (do not reinterpret as completed live integration)

- Original app: `127.0.0.1:18731` returns HTTP 200; BUILD 03 rehearsal: 18733 returns 200; BUILD 03.5 isolated rehearsal: 18734 returns 200 and has its own PostgreSQL, advisor tables and synthetic judge data.
- Root `.env` has a present `BACKBOARD_API_KEY`, but is production/SQLite and lacks `APP_KEY` and the expected domain schema. Running ad hoc `php artisan` from this environment is unsafe as a proxy for the running app. Never invent, regenerate, copy or rotate its `APP_KEY`, passwords or tokens.
- BUILD 03.5's 18734 rehearsal has its valid **separate** `APP_KEY` but no Backboard key. Its adapter is disabled; no live generation was tested. Keep 18731/18733/18734, all databases, volumes and existing secrets intact.
- Exactly one authenticated model-catalog GET in previous preflight returned no validated list, and the reason (TLS vs HTTP/auth/API schema) was not classified. No model/provider or pricing is verified.
- Default account assistant isolation is unverified. Official Backboard send-message API supports passing `assistant_id` explicitly. Do NOT accidentally route through an account's first/default assistant.
- Operator identified Windows file ACLs permitting broad local-read access on three private environment files. Treat as a security blocker, but never perform blanket ACL changes without reviewing effective access and confirming the owner will retain access.
- Neither hackathon promo redemption nor paid generation is proven. Do not claim otherwise.

## Absolute guardrails

1. **No paid/generative API requests:** no `POST /threads/messages`, no `scope:backboard-smoke`, no threads, no assistant creation/update/deletion, no auto-retry. **No remote writes or deletes.** No checkout, host/DNS, Git remote/push/deploy, or partner/promo action.
2. Only narrowly necessary read-only HTTP GETs to the authenticated official Backboard API are allowed, **once per intended endpoint**, after normal TLS verification succeeds. They must use `X-API-Key` in an in-memory request, not shell literal arguments/history. Do not fetch full private messages, memories, documents or account data.
3. Never print or copy secrets, `.env` content, Authorization headers, credential-bearing URLs, assistant system prompts, provider response bodies or thread/assistant UUIDs to console, commit, chat, JSON evidence or screenshots. Never turn TLS verification off (`verify=false`, `-k`, `--insecure`, etc. are forbidden). Never set wildcard trusted proxies.
4. Preserve working `APP_KEY`, DB identity and data; no `migrate:fresh`, `db:wipe`, `down -v`, rollback, seed or data-reset outside named disposable test DBs. No migration against the owner's original database. No build/deploy onto the 18731 or 18733 installations.
5. If an environmental/private-file modification is needed (Windows ACL, `php.ini`, private `.env`, runtime key injection, Docker service recreation), **document the exact minimal change and ASK the operator to approve it**. Do not apply that operation unattended. Ordinary source-code changes and tests on a feature branch are allowed.
6. Failure-safe means provider remains disabled, the deterministic Replay Lab stays fully functional and no live network generation is claimed.

## Phase A — Map real environments and classify TLS, without leaking secrets

1. Confirm HEAD, branch and clean working tree; if safe, create `feat/build-03-6-backboard-connectivity` from `fea6281`. If the tree has uncommitted work, stop and ask rather than discard it.
2. Read `AGENTS.md`, `docs/BACKBOARD_INTEGRATION.md`, the Build 03.5 report, current config and adapter, production Compose env allowlist, the test scripts and guardrails.
3. Inventory the three running services and identify their config source **by metadata and secret-presence booleans only**. Distinguish Windows CLI's root `.env` from Docker-rehearsal env files. Do not display env values or extract APP_KEY values to reports.
4. Diagnose CLI PHP trust using `php --ini`, `ini_get('curl.cainfo')`, `ini_get('openssl.cafile')`, `openssl_get_cert_locations()`, `curl_version()` (TLS backend), and existence/readability of a normal trusted CA bundle. Empty INI values do **not** alone prove a TLS failure. Check system curl and isolated runtime certificate validation separately if useful.
5. Observe only sanitized error class: DNS, timeout, TLS-chain/verification, HTTP 401/403/404/422/429/5xx, invalid JSON, or valid JSON with unexpected schema. Keep certificate verification enabled. If the actual issue is an absent trusted CA and a **verified official CA bundle** is needed, document the source, exact absolute path and correct local/isolated configuration change; do not silently edit machine-wide php.ini or unapproved private env.
6. Windows ACL: inspect identity/inheritance/effective READ for `Everyone`, `Users`, `Authenticated Users`, and unrelated principals for the root `.env` and two private rehearsal env files; report only `PRIVATE/BROAD/UNKNOWN` per file and an explicit *target ACL plan*. Preserve the current user, required runtime/Docker access, Administrators and SYSTEM as appropriate; do not blindly run `icacls /reset` or disable all inheritance. **No ACL modifications without approval.**

## Phase B — Read-only model/assistant discovery (only after verified TLS)

Use official documentation (no guessed endpoints):

- `GET https://app.backboard.io/api/models?model_type=llm&supports_json_output=true&limit=50` with `X-API-Key`. Expected top-level shape is `models` array and `total`. Read `provider`, `name`, `model_type`, `supports_json_output`, `input_cost_per_1m_tokens`, `output_cost_per_1m_tokens`, optionally `context_limit`/`max_output_tokens`. Validate types and ensure prices are nonnegative USD per million tokens. If no usable list, classify the exact sanitized status/error; **do not invent model names or prices.** Select up to three economical verified candidate models (avoid expensive reasoning/multimodal) as a recommendation, not live configuration. One authenticated GET at most for this endpoint.
- `GET https://app.backboard.io/api/assistants?skip=0&limit=20` with the same secure conditions, once at most. Do not print assistant IDs, names, descriptions, tools, documents or prompts. It can establish inventory but **cannot** prove the default assistant is private. The safe production plan is a dedicated ScopeTwin assistant pinned by explicit `assistant_id`, created only after separate authorization. Do not create one now.
- If the key is invalid/restricted, report `KEY_AUTH_FAILED` and stop discovery. Do not show, rotate, or recreate the key. A previous catalog failure must not turn into a flurry of requests.
- GET-only reads cannot guarantee generation is free of all metering, so do not assert account credits were unchanged unless verified by the user.

## Phase C — Local source hardening: bind to explicit assistant ID

1. Read the current `ReplayAdvisor` implementation and current fixed request contract. Official `POST /threads/messages` accepts `assistant_id` on the first message; omitting it can select or create a default assistant. For this project's **enabled** integration, require a syntactically valid, explicit server-side `BACKBOARD_ASSISTANT_ID` UUID. Never default to an existing account assistant; missing/invalid ID must stop with deterministic fallback and **zero provider calls**.
2. Introduce `BACKBOARD_ASSISTANT_ID` as a **server-only** configuration option in `config/backboard.php`, `.env.production.example`, and the Compose variable allowlist. Never use `VITE_`, browser props or rendered HTML for this value. Handle configuration caching/recreation in runbook.
3. Send the exact configured UUID only as the JSON `assistant_id` field. Never log this ID. Leave memory, web search, tools and multimedia off. Never change the deterministic ALLOW/REVIEW/DENY engine or approval behavior. Respect existing timeouts, three-per-org daily max, one-per-user rolling 60 seconds, cache, fallback and cleanup semantics. Do not silently auto-create assistants.
4. Add automated faked-HTTP tests proving: disabled/missing/malformed assistant ID => no upstream call; enabled valid assistant ID => the payload includes `assistant_id` and all privacy flags; no token/assistant ID leaks to public UI, JSON props or logs; wrong/foreign permissions remain 403/404; existing replay and approval records remain unchanged; unexpected API errors fall back safely.
5. Do not run migrations on the owner's original databases. If no schema change is needed, explicitly say so.
6. Update `docs/BACKBOARD_INTEGRATION.md`, `docs/GO_LIVE_CHECKLIST.md` and the forthcoming video/Devpost integrity notes to say `Backboard live call: NOT TESTED` until proven.

## Phase D — Verification and handoff

- Execute the existing SQLite and isolated PostgreSQL regression suites (baseline BUILD 03.5: 169 tests / 1291 assertions; new assertions must increase unless pure docs), concurrency tests, TypeScript/build/lint/format/Pint, Composer/npm audits, Chromium/production browser suites as practicable. If any test cannot run, state precisely why; do not pretend PASS.
- Compare original `.env` and original user database preservation guards. Preserve ports 18731/18733/18734 and the verified working demo. Source commit on feature branch only, after tests; never push.
- Never put private env files or model catalog raw JSON in Git. Run scans on staged changes.
- Produce `docs/BUILD03_6_CONNECTIVITY_REPORT.md` with a concise sanitized table:
  `ENV_MAP`, `TLS_DIAGNOSIS`, `CA_ACTION_NEEDED`, `CATALOG_HTTP_STATUS`, `KEY_AUTH_STATUS`, `MODEL_CANDIDATES` (only validated public provider/model and price), `ASSISTANT_INVENTORY_STATUS`, `EXPLICIT_ASSISTANT_BINDING`, `ACL_STATUS` / proposed action, `18734_CONFIG_READY`, `LIVE_GENERATION_STATUS=NOT_TESTED`, `CREDITS_SPENT=NOT_CLAIMED`, tests/results and `OPERATOR_APPROVALS_REQUIRED`.
- Clearly state the shortest **next authorized step**: operator signs off minimal ACL/CA/private-runtime changes; user creates or approves creation of a dedicated assistant; owner supplies its UUID privately; actual provider/model selection based on catalog; then separately authorized single synthetic live `scope:backboard-smoke` from isolated `18734` only. DO NOT RUN IT NOW.

## Source contracts checked on 2026-10-18

- https://docs.backboard.io/api-reference/models/list
- https://docs.backboard.io/concepts/models
- https://docs.backboard.io/api-reference/assistants/list
- https://docs.backboard.io/api-reference/threads/send-message
- https://docs.backboard.io/api-reference/threads/delete
- https://www.php.net/manual/en/curl.configuration.php
- https://curl.se/docs/sslcerts.html

**Stop condition:** If safe catalog discovery still fails, report a classified blocker. Do not pivot to unsafe TLS, guessed model, existing/default assistant, or live generation. Preserve existing code and working demos throughout.
