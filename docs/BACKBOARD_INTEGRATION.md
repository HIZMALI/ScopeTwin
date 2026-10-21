# Optional AI Replay Advisor

**Production status: disabled (`BACKBOARD_ENABLED=false`).** No Backboard credential is installed in the public preview. Separately authorized isolated live attempts did not produce an accepted explanation; the latest returned HTTP 200 with provider status `FAILED` before output validation. Thread cleanup was confirmed. Successful live LLM advice and actual billed cost remain unverified. The public demo and video make no AI-success claim.

## Authorization and data boundary

An OWNER/ADMIN may explicitly request advice with a session/CSRF-protected `POST /replays/{id}/advice` when privately enabled. Foreign replay IDs return 404; VIEWER mutations return 403. GET navigation and public demo routes never call the adapter. Input beyond CSRF is rejected.

[`ReplayAdvisor`](../app/Services/ReplayAdvisor.php) sends only frozen aggregate counts: sample size, deterministic effect transitions, changed, newly denied/reviewed and unchanged. No raw requests, UUIDs, policy definitions, customer content, names, emails, domains, amounts or currencies are sent. Aggregate input is bounded to 2 KiB and response transport/body to 32 KiB. Manifests contribute only to a local cache fingerprint.

The LLM cannot grant privileges, change policy, approve/reject a request or alter deterministic replay facts. Validated advisory text is escaped in React. Counts are checked against deterministic aggregates; bounded output validation rejects unsupported claims and unsafe content, but is not a general semantic safety guarantee.

## Provider contract

The server calls `POST https://app.backboard.io/api/threads/messages` with `X-API-Key` and an explicit assistant ID. It sends `llm_provider`, `model_name`, `json_output=true`, `stream=false`, `memory=off`, `web_search=off`, `tools=[]`, and disabled image/video generation. There is no account-default assistant fallback.

A response must match the configured assistant, contain a valid thread identity, have status `COMPLETED`, request no tools and provide valid bounded advice JSON. `content` may itself be a JSON string. Safe diagnostics classify HTTP status, transport/provider/parsing/validation stage and a finite validation reason; raw upstream content, identifiers and credentials are not logged.

A returned thread identity enables one best-effort DELETE in `finally`, including after invalid output. Timeouts or absent/malformed IDs can leave an orphan requiring private operator inspection. DELETE confirmation does not establish erasure of provider logs, backups or upstream retention. Request-level memory/search settings do not establish provider-wide zero retention.

## Configuration and quotas

Server-only configuration is in [config/backboard.php](../config/backboard.php). Never create `VITE_` equivalents or expose values in Inertia props.

| Variables                                                  | Purpose                                                                                  |
| ---------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `BACKBOARD_ENABLED`                                        | Disabled by default; explicit operator enablement only                                   |
| `BACKBOARD_API_KEY`, `BACKBOARD_ASSISTANT_ID`              | Private key and verified dedicated assistant binding                                     |
| `BACKBOARD_LLM_PROVIDER`, `BACKBOARD_MODEL_NAME`           | Explicit provider/model; verify current catalog and account access before enabling       |
| `BACKBOARD_TIMEOUT_SECONDS`                                | Bounded request timeout; default 12 seconds                                              |
| `BACKBOARD_DAILY_CALLS`, `BACKBOARD_USER_CALLS_PER_MINUTE` | May lower the hard caps of 3 attempts/organization/UTC day and 1/user/rolling 60 seconds |
| `BACKBOARD_CACHE_SECONDS`                                  | Default 15-minute shared cache; bounded 1 minute–1 hour                                  |

The isolated rehearsal previously verified the catalog entry `featherless / openai/gpt-oss-20b`; a catalog record does not prove generation access or price stability. No model-specific cause of the failed live attempt was established. Do not infer LLM access from memory/RAG-only credits.

Quota is reserved under user/organization locks before HTTPS. Failed, rejected, rate-limited, timeout and invalid-response attempts consume quota. Cache identity includes workspace, frozen evidence, provider/model, assistant and prompt version. There is no automatic generation retry; later deliberate requests after expiry may spend another attempt. Ambiguous timeouts can still be billed: call quotas are **not a dollar cap** or exactly-once guarantee.

`advisor_explanations` / `advisor_quotas` store validated output, hash/lease state and counters. Expiry is validity, not deletion; no retention scheduler is installed. PostgreSQL concurrency is tested; SQLite can return busy under unusual write contention.

## Operator-only live verification

Any new generation, its thread and cleanup require separate explicit spending authorization. Use only an approved isolated `scopetwin_*demo` / `scopetwin_*rehearsal` fixture, preserve existing keys/data, verify assistant isolation, model/account availability and cached configuration privately, then permit at most the approved number of attempts.

```sh
php artisan scope:backboard-smoke --live-backboard-smoke --confirm-charge-credits
```

Both flags are required. The command validates the fixed synthetic aggregate, enforces quotas and a protected local attempt ledger, and prints sanitized outcome/cleanup diagnostics rather than advice or identifiers. Missing flags/configuration or cache reuse is not a live test. Restore disabled configuration after the approved test and confirm deterministic data is unchanged. Automated tests use fake HTTP and forbid stray requests; see [testing](TESTING.md).

## Official API references

[Send message](https://docs.backboard.io/api-reference/threads/send-message), [message concepts](https://docs.backboard.io/concepts/messages), [thread lifecycle](https://docs.backboard.io/concepts/threads), [assistant creation](https://docs.backboard.io/api-reference/assistants/create), [model discovery](https://docs.backboard.io/concepts/models), [billing](https://docs.backboard.io/concepts/billing).
