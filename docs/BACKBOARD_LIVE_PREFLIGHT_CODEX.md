# ScopeTwin — Backboard live preflight (zero billable model calls)

## Objective
Prepare the existing, verified ScopeTwin BUILD 03.5 (`fea6281`, branch `feat/build-03-5-backboard-advisor`) for **one separately authorized** Backboard live smoke test. DO NOT execute that smoke test or any text-generation request in this task. Preserve the next BUILD 04A video/release work; do not start it here.

## Context to read first
- `docs/BUILD03_5_BACKBOARD_REPORT.md`
- `docs/BACKBOARD_INTEGRATION.md`
- `docs/DEPLOYMENT.md`
- `docs/OPERATIONS_RUNBOOK.md`
- `config/backboard.php`
- `app/Console/Commands/BackboardSmoke.php`
- `compose.production.yaml`
- Current Git branch/status and available local rehearsal environments.

## Non-negotiable constraints
1. **NO model-generation or charge-confirmed requests.** Never execute `scope:backboard-smoke --live-backboard-smoke --confirm-charge-credits` during this preflight, never issue POST `/threads/messages`, and never create/delete any remote thread, assistant, or credential.
2. **NO user-data modifications.** Never run `migrate:fresh`, `db:wipe`, `down -v`, destructive SQL, blanket cache/session resets or migrations against the user's original `scopetwin` database. No changes to existing production / development data, keys, passwords or ports.
3. **Secrets:** Do not print, copy into output, include in screenshots, commit, echo in commands, send to the chat or write to logs any API key, `APP_KEY`, DB password, environment file or authentication header. Do not include even redacted partial key prefixes. For config diagnostics report only `PRESENT`, `MISSING`, `INVALID`, or `CONFLICT`, plus source *file names* if safe. Never log full request/response payloads from authenticated endpoints.
4. Never overwrite `.env` or create a new application key for an existing database. A new random key is allowed **only** in a fresh isolated disposable rehearsal environment, and only if needed. Protect `.env` and private rehearsal secrets in ignored files with appropriate permissions.
5. Do not push, deploy, publish a video, modify DNS, buy credits, enable auto-recharge or change Backboard account settings. Make no claims about sponsor-credit eligibility; the promo was inactive for this participant.
6. Preserve core deterministic `ALLOW / REVIEW / DENY`, the historical Replay Lab, approvals and its strict non-execution boundary.

## Tasks
### A. Read-only environment reconciliation
- Check the state of the existing app on loopback `18731`, the BUILD 03 production rehearsal on `18733`, and the BUILD 03.5 isolated rehearsal on `18734` without changing them.
- Examine the local application's *effective* config and ignored env sources privately (never print values). The previous report said the root `.env` selected `APP_ENV=production` / SQLite and lacked `APP_KEY` and the expected ScopeTwin domain schema. Determine whether this is still true and whether `BACKBOARD_API_KEY` is present in the user-chosen private file. **Distinguish `APP_KEY` from `BACKBOARD_API_KEY`.**
- Identify the safest isolated environment for a synthetic live smoke, preferably the already verified 18734 dedicated rehearsal database, and determine whether Build 03.5's two additive tables and the fixed judge demo fixture are available there. Use only read-only checks. Do not copy old data or secrets into that environment.
- If any prerequisite is missing or conflicts, report the exact safe next action without attempting a workaround that could touch an existing database or rotate a key.

### B. Authenticated provider/model preflight — read-only only
- Use the locally stored Backboard key solely server-side. If present, make **at most one** authenticated GET to `https://app.backboard.io/api/models?model_type=llm&supports_json_output=true&limit=100` (or choose an equivalent catalog read in the authenticated dashboard). TLS verification on, short timeout, no redirect following, no request/body logging. If the key is missing or invalid, stop and report a status only.
- Read catalog fields and select at most 3 low-cost text candidates with verified `provider`, exact `name`, JSON-output support, and input/output prices; do not guess models or claim account access/prices from public examples. Output ONLY those non-secret model-selection facts, not the full authenticated API response.
- Prefer a modest, reliable JSON-capable text model compatible with the adapter. Do not modify runtime config, enable the advisor or send a message yet.
- Check whether Backboard's default assistant selection could associate a future request with existing assistant memory/documents/tools. If this cannot be verified without risky modifications, flag as a privacy blocker for live testing. Do not access or display assistant private content.
- Validate current vendor API documentation contract for the adapter (method, headers, fields, deletion and response constraints); report incompatibility without making a message request.

### C. Prepare an explicit one-call runbook for user approval
- State which **disposable** DB/port will be used, what private config variables must be set, which existing key will be read (name only), the chosen provider/model and published prices, and how to ensure the request will use only fixed synthetic replay aggregates.
- Describe the exact guarded smoke command but **do not execute** it until the account owner gives new explicit consent for a possible credit-consuming call.
- Explain that one generation plus thread cleanup may involve multiple HTTP requests, timeouts may still be billable, the 3/day call quota is not a dollar cap, and no automatic retry is allowed.
- Suggest a bounded before/after inspection of Backboard dashboard usage/credits (user action; do not attempt paid plan or automatic top-up).

### D. Verification and reporting
- No code change is necessary if preflight succeeds. Do not create a new Git branch/commit just for a read-only diagnostic. If a harmless source fix is genuinely required, propose it and stop for review instead of changing the verified release.
- Produce a concise **Turkish** report with: `LOCAL_ENV_STATUS`, `APP_KEY_STATUS`, `BACKBOARD_KEY_STATUS`, `REHEARSAL_READY`, `MODEL_CATALOG_STATUS`, `MODEL_CANDIDATES`, `DEFAULT_ASSISTANT_PRIVACY_STATUS`, `LIVE_NOT_TESTED`, and the recommended next step. Never expose secret values.
- Preserve the clean tree and all previous BUILD 03/03.5 services; include what was *actually* checked, and distinguish test evidence from assumptions.

## Important release sequencing
BUILD 04A's older prompt predates the advisor branch. When it is run later, its implementation baseline must include `fea6281` (and any subsequently approved fixes), not silently fork from BUILD 03 `643497d` and drop Backboard integration. Do not begin BUILD 04A now.
