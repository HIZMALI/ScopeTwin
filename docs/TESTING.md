# Testing and verification

Test results are dated observations, not a security certification or a capacity guarantee. The latest application change is `20f9a7d`; `f5c6b0b` changes only release documentation. Historical detailed build records remain in Git history, including the [pre-cleanup documentation tree](https://github.com/HIZMALI/ScopeTwin/tree/f5c6b0be417f89ea3cbddd99199da6ab77154c53/docs).

## Recorded release evidence

| Date / scope                                                         | Observed result                                                                                                                                                                            |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 21 Oct 2026, final application, local SQLite                         | 212 tests / 1,615 assertions passed, 9.82 seconds                                                                                                                                          |
| 21 Oct 2026, GitHub Linux CI at `f5c6b0b`                            | [Tests](https://github.com/HIZMALI/ScopeTwin/actions/runs/39503448296) and [linter](https://github.com/HIZMALI/ScopeTwin/actions/runs/39503448249) succeeded; 212 tests / 1,615 assertions |
| 21 Oct 2026, real Render/PostgreSQL application                      | 3 Chromium scenarios passed in 1.1 minutes                                                                                                                                                 |
| 20 Oct 2026, isolated PostgreSQL regression before final proxy fixes | 209 tests / 1,604 assertions passed; migration reapply and idempotency/approval/advisor concurrency checks passed                                                                          |
| 21 Oct 2026, frontend and packaging                                  | TypeScript, ESLint, Prettier, Pint, strict Composer validation and Vite build passed; Composer advisories 0, npm vulnerabilities 0 at that check                                           |

The live PostgreSQL-backed browser checks covered registration/login, one-time agent key issuance, policy publishing, ALLOW/REVIEW/DENY, idempotent retry/conflict, frozen replay (2 changed / 2 newly denied), documentary approval, scoped audit JSON and usage (3 requests / 1 approval / 1 replay). A second workspace could not read or mutate the first workspace's decisions, replays, approvals or audit records.

External Chromium checks used 1440px and 390px layouts, keyboard/skip-link/dialog navigation, axe WCAG 2/2.1 AA rules and CSP/JavaScript error monitoring. Observed security responses included valid HTTPS/HTTP redirect, HSTS, Secure/HttpOnly/Lax cookies, blocked private paths, rejected wrong Host, CSRF 419, anonymous API 401 and public mutation 405. Spoofed forwarded/client-IP/port/prefix headers could not bypass the tested limits or poison generated URLs after the final proxy fix.

External PostgreSQL access was closed after provisioning: an operator connection was rejected while internal app readiness stayed healthy. Runtime credentials had no superuser, role/database creation, schema CREATE or ownership. This verifies that installation, not arbitrary deployments.

## Reproduce without a database

From the repository with installed locked dependencies:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Unit/PolicyEvaluatorTest.php
npm run types:check
npm run lint:check
npm run format:check
php vendor/bin/pint --test
composer validate --strict
composer audit
npm audit
```

The evaluator test is a pure PHPUnit test: no Laravel database boot or migration. The entire Unit suite also contains a starter test using `RefreshDatabase`; do not assume `--testsuite Unit` is database-free. Dependency audits report current advisories when run; their results can change.

## Full application and PostgreSQL tests

Use a private development/test installation only. Confirm no inherited production `DB_*` variables or cached production configuration override the test target.

- `php artisan test --compact` uses the SQLite in-memory settings in [phpunit.xml](../phpunit.xml), an ephemeral test encryption key and fake HTTP with stray requests forbidden.
- [`scripts/test-postgres.ps1`](../scripts/test-postgres.ps1) requires the **development** Compose PostgreSQL service and selects `scopetwin_test`. It runs the suite, rolls back/reapplies the three domain migrations there, and runs separate idempotency, approval and advisor concurrency scripts. It writes to that test database; never redirect it to a user/production database.
- `npm run test:browser` uses [Playwright configuration](../playwright.config.ts), loopback 18732 and `scopetwin_browser_test`. Its setup migrates/resets that disposable database. Install Chromium with `npx playwright install chromium` first; do not point it at an existing user database.
- The optional advisor HTTP and browser fixtures are mocked contract tests. They do not prove a successful live LLM response and never authorize a credit-consuming smoke test.

Exact assertions live under [tests](../tests). The [acceptance contract](ACCEPTANCE_TESTS.md) describes the required domain/security invariants.

## Production packaging and recovery checks

[`DEPLOYMENT.md`](DEPLOYMENT.md#reproduce-the-isolated-local-rehearsal-powershell-7) documents the opt-in Docker rehearsal on 18733, its private configuration and dedicated PostgreSQL volumes. `npm run test:release` targets that installation and creates synthetic records during authenticated scenarios. The suite covers Chromium, Firefox and WebKit. In the recorded Windows environment WebKit link-Tab traversal was unavailable; programmatic focus was checked there, and actual link traversal in Chromium/Firefox.

Container scanning (`scripts/scan-containers.ps1`), fresh database-image initialization, benchmark/load and backup/restore scripts are **operator actions on disposable targets**, not part of a documentation check. Historical local scans reported 0 HIGH/CRITICAL findings; they are not an independent scan of the deployed Render image. The 18 Oct isolated recovery drill compared counts/hashes across 15 domain tables; this is not a current managed-host backup or a recovery SLA.

Never run `migrate:fresh`, `db:wipe`, `down -v` or restore over existing data. Follow the [operations runbook](OPERATIONS_RUNBOOK.md).

## Documentation verification — 21 October 2026

The documentation cleanup runs no database migrations, Docker operations or Render configuration changes. Fresh pure evaluator/static/link/secret-audit results are recorded in the [maintenance record](IMPLEMENTATION_LOG.md). Earlier SQLite, PostgreSQL and live browser counts above are retained as historical evidence, not rerun claims.

## Remaining verification limits

Successful live Backboard generation and actual API cost remain unverified; production advice is disabled. Mail delivery/recovery, independent penetration testing, formal accessibility certification and commercial load/SLA are not established. A 16-minute quiet-client check preserved the demo and returned the next page in 1.149 seconds, but provider suspension was not observed; it is not a measured cold boot. Free hosting/database expiry and backup limitations are documented in [deployment](DEPLOYMENT.md#actual-render-preview---2026-10-21).
