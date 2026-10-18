# AI Replay Advisor — BUILD 03.6

**Backboard live call: NOT TESTED.** BUILD 03.6 adds mandatory explicit assistant binding and mocked verification. One authenticated model-catalog GET verified three candidate records and advertised prices; one assistant-inventory GET returned HTTP 200 but its inventory shape was not validated. No generation, remote write/delete or account change occurred. No runtime model is selected; balance/credit changes are not claimed. Decisions, replay, documentary approvals and the public judge tour work without AI. See BUILD03_6_CONNECTIVITY_REPORT.md for the measured findings and operator approvals.

## Boundary

An OWNER/ADMIN explicitly clicks **Explain with AI** on an authenticated frozen replay report. `POST /replays/{id}/advice` uses session authentication, CSRF and identity-derived organization scope. Unknown/foreign IDs return 404; VIEWER returns 403. Input other than CSRF is rejected. GET navigation never generates advice. Anonymous `/demo` and `/demo/replay` remain stateless GET/HEAD with no adapter calls or writes.

`ReplayAdvisor` transmits only a validated aggregate of saved deterministic results: simulated label, sample count, nine effect transitions, changed, newly denied/reviewed and unchanged. No UUIDs, raw samples, customer content, emails, names, domains, amounts/currencies, policy definitions, audit events or browser input are sent. Manifests/fingerprints contribute only to the local SHA-256 cache key. Aggregate JSON is capped at 2 KiB; response transport/body at 32 KiB.

The fixed server-side endpoint is `POST https://app.backboard.io/api/threads/messages`, with `X-API-Key` and an explicit server-only `assistant_id`. Missing/malformed `BACKBOARD_ASSISTANT_ID` produces fallback and zero upstream calls, including before cache access. There is no fallback to the account's first assistant. The returned assistant identity must match the configured binding; mismatched/missing identity rejects advice while still attempting thread cleanup. The request explicitly disables memory, search, tools, streaming, image/video generation and requests JSON output. No redirects, conversation reuse or automatic retries. The fixed prompt prohibits invented causes, prevented-loss/compliance claims, authorization recommendations and transaction instructions.

Output has exactly `explanation` and `questions`, at most 900 ASCII visible characters, two or three explanation sentences and up to two short questions. Numerals, markup, URLs, credentials and selected action/guarantee terms are rejected. This conservative lexical filter can reject legitimate responses and is not a semantic safety proof. Accepted output remains untrusted advisory text rendered through React escaping with **AI-generated explanation (Backboard), not a policy decision**. Numeric facts always come from deterministic replay. No AI text controls authorization or writes to domain records.

## Private setup and model selection

Apply the additive migration through the normal explicit owner/migration procedure before enabling: `php artisan migrate --force`. It creates only advisor tables and leaves existing records untouched. Runtime permissions need SELECT/INSERT/UPDATE/DELETE on those tables. No migration or environment rewrite was run against the user's application database during this build; fresh/reversible migration checks used disposable databases.

1. Keep disabled until explicitly authorized for spending. Create a key privately in Backboard **Settings → API Keys**. Never paste it into chat, source, CLI arguments, browser storage, logs or screenshots. Use an ignored operator environment or secret manager; do not overwrite the application's existing environment, APP_KEY or DB_PASSWORD.
2. In the authenticated official model catalog/dashboard, verify a currently available economical **text** provider/model, JSON output support, input/output unit prices and limits. The documented authenticated `GET /api/models?model_type=llm&supports_json_output=true` lists these capabilities/prices. Examples do not prove availability. No default model/provider is guessed here. Do not print authenticated responses into build evidence.
3. Obtain separate authorization to create a dedicated empty ScopeTwin assistant; none was created in this build. The owner must privately inspect its documents, memory and tools and supply its UUID as `BACKBOARD_ASSISTANT_ID`, never through chat or screenshots. Inventory alone cannot establish isolation. The endpoint would otherwise select/create a default assistant; this implementation prevents that path. Binding does not prove absence of provider logging or accidental future configuration changes. The adapter never creates, updates or deletes assistants.
4. Configure these **server-only names** privately, only after operator approval: `BACKBOARD_ENABLED`, `BACKBOARD_API_KEY`, `BACKBOARD_ASSISTANT_ID`, `BACKBOARD_LLM_PROVIDER`, `BACKBOARD_MODEL_NAME`, `BACKBOARD_TIMEOUT_SECONDS`, `BACKBOARD_DAILY_CALLS`, `BACKBOARD_USER_CALLS_PER_MINUTE`, `BACKBOARD_CACHE_SECONDS`. See disabled conservative defaults in `config/backboard.php` / `.env.production.example`. Never create `VITE_` equivalents. Configuration is cached at container boot; only an approved isolated-runtime recreation refreshes it. Do not clear caches/recreate existing services during a read-only diagnostic. Missing/invalid settings cause zero calls. Production Compose passes only the explicit allowlist; Inertia props expose no key, assistant ID or model settings. No new schema migration is needed for BUILD 03.6.
5. Monitor actual balance and usage in the Backboard dashboard. Do not enable auto-recharge or purchase credits. The documented ordinary initial credit normally expires after thirty days; no account-specific balance/expiry is claimed. Core workflows retain deterministic fallback after credit exhaustion.

## Quota and failure semantics

Maximum three generation attempts per organization per UTC day and one per user per rolling sixty seconds. Configuration can lower these limits, including zero, but cannot raise these initial caps. Failed/auth/credit/rate-limited/timeout/invalid-response attempts consume quota. These are **call quotas, not a dollar cap or billing guarantee**. The documented endpoint does not expose a general max-output-token request field; no token billing cap is claimed. Thread DELETE is an additional provider request.

A short DB transaction locks user then organization, checks the cache and reserves quota. HTTPS runs after commit. The shared cache identity includes organization, frozen baseline/candidate fingerprints, sample manifest, aggregate, provider/model, normalized explicit assistant identity and prompt version. Changing the assistant cannot reuse earlier advice or bypass quota. Default TTL is fifteen minutes, bounded to one minute–one hour. Identical clicks reuse success/failure/pending state; no automatic polling/retry. A later explicit click after expiry can spend another attempt. Process death can leave a pending lease until expiry; ambiguous timeouts can still be billed. Exactly-once remote processing cannot be guaranteed. PostgreSQL tests exercise real multi-process races; SQLite unusual write contention may return a busy error. PostgreSQL remains the production target.

Logs contain finite status classes/timings only. Separate advisor tables hold validated text, hashes, validity/cleanup flags and quota counters. No raw provider response or key is persisted. Expiry governs validity, **not erasure**. No retention scheduler was added. Operators may remove expired advisor rows through normal retention procedures; never remove active reservations or unexpired quotas.

## Provider persistence and cleanup

Every request creates a new thread. A valid returned thread UUID enables one `finally` DELETE to `https://app.backboard.io/api/threads/{thread_id}`, including after invalid model output. Cleanup failure produces a sanitized warning and unconfirmed flag. Timeout, absent ID or oversized/malformed response may prevent cleanup entirely; inspect orphans privately in the provider dashboard. A response without a valid thread ID is rejected. Successful DELETE confirms the documented thread deletion request, **not** destruction of provider operational logs, backups, assistant defaults or upstream retention. No compliance guarantee is claimed.

## Separately authorized live smoke

Use only a dedicated `scopetwin_*demo` / `scopetwin_*rehearsal` database with the fixed synthetic `scope:judge-demo --confirm-disposable` fixture. After private setup and explicit human authorization for one credit-consuming generation:

```powershell
php artisan scope:backboard-smoke --live-backboard-smoke --confirm-charge-credits
```

The command validates the exact synthetic aggregate, uses existing quotas, calls once, attempts cleanup and prints only SUCCESS/FAIL and cleanup state. No model output/key/thread IDs. Both flags are mandatory; absent flags, disabled/unconfigured integration or cache reuse yields LIVE_NOT_TESTED. **The charge-confirmed command was not run during this build.** Automated tests fake HTTP and prevent stray requests.

## Submission and eligibility

The participant reported that the restricted Galuxium partner promotion was inactive in their account. This remains unresolved. Ordinary initial account credit does not establish partner redemption or hackathon eligibility. No restricted promotion string is stored here. Confirm with the organizer through the owner's account. Never present fabricated/mock output as a real provider response in Devpost/video. Mocked UI artifacts are engineering fixtures only. The anonymous tour remains provider-independent.

## Official contract sources

- [Quickstart](https://docs.backboard.io/quickstart)
- [Send message](https://docs.backboard.io/api-reference/threads/send-message)
- [Thread lifecycle/deletion](https://docs.backboard.io/concepts/threads)
- [Model discovery](https://docs.backboard.io/concepts/models)
- [Billing](https://docs.backboard.io/concepts/billing)

Reviewed 2026-10-18. Catalog-advertised prices/candidates were read under verified TLS; live generation, balance and assistant isolation remain unverified. Actual BUILD 03.6 evidence and the proposed CA/ACL/private-runtime changes: `BUILD03_6_CONNECTIVITY_REPORT.md`. Historical BUILD 03.5 evidence remains in its separate report.
