# Maintenance record

## Documentation cleanup — 21 October 2026

Starting point: clean `main` at `f5c6b0be417f89ea3cbddd99199da6ab77154c53`, identical to the expected GitHub remote. Application code remains the verified `20f9a7d` release. This change edits documentation only; no local database, migration, Docker or Render operation is part of the cleanup.

All root documentation and every document under `docs/` were inspected for audience, currency, duplication and inbound references. A repository-wide reference scan found obsolete prompt/report dependencies only in other documentation, not executable application/tests/scripts. The audio scripts directly read `docs/VIDEO_SCENES.json`; it, the final captions and all four referenced screenshots are preserved at their existing paths. CI, package manifests/lockfiles, license, scripts and application files are preserved.

README is now the single entry point. Current architecture, API, installation/security/operations, acceptance criteria, reproduction commands and dated release evidence replace overlapping sprint narratives. The Devpost draft uses actual website/demo/replay/source/video URLs and preserves the documentary-only approval, proposed-pricing and disabled/unverified AI limitations. The Laravel starter/dependency attribution and AI-assisted development provenance remain explicit.

### Retired documents

Removed from the active tree because they are completed task instructions, duplicate entry points/plans, superseded intermediate reports or an outdated product brief. Unique operational/domain details are retained in the current architecture, deployment, operations, Backboard and testing guides.

- `README_START_HERE.md`
- `docs/BACKBOARD_CONNECTIVITY_FIX_CODEX.md`
- `docs/BACKBOARD_LIVE_PREFLIGHT_CODEX.md`
- `docs/BACKBOARD_FIX_REPORT.md`
- `docs/BUILD01_CODEX_PROMPT.md`
- `docs/BUILD02_CODEX_PROMPT.md`
- `docs/BUILD02_PLAN.md`
- `docs/BUILD02_TECHNICAL_NOTES.md`
- `docs/BUILD03_CODEX_PROMPT.md`
- `docs/BUILD03_HIZLI_KONTROL.md`
- `docs/BUILD03_TECHNICAL_NOTES.md`
- `docs/BUILD03_TEST_EVIDENCE.json`
- `docs/BUILD03_5_BACKBOARD_CODEX_PROMPT.md`
- `docs/BUILD03_5_BACKBOARD_REPORT.md`
- `docs/BUILD03_6_CONNECTIVITY_REPORT.md`
- `docs/GO_LIVE_CHECKLIST.md`
- `docs/PRODUCT_BRIEF.md`
- `docs/PUBLIC_RELEASE_REPORT.md`
- `docs/SUBMISSION_DELIVERY_REPORT.md`

The original records remain available in the [pre-cleanup documentation tree](https://github.com/HIZMALI/ScopeTwin/tree/f5c6b0be417f89ea3cbddd99199da6ab77154c53/docs) and normal Git history (`git show f5c6b0b:<path>`). Removal does not erase development history, licenses, attribution or failed integration evidence. No history rewrite is used.

### Verification performed in this cleanup

| Command / check                                       | Actual result                                                                                                                                                               |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Pure evaluator PHPUnit file                           | 13 tests / 29 assertions PASS, 0.442 seconds; no Laravel database bootstrap                                                                                                 |
| `npm run types:check` / `npm run lint:check`          | PASS                                                                                                                                                                        |
| `npm run format:check` / `php vendor/bin/pint --test` | PASS                                                                                                                                                                        |
| `composer validate --strict`                          | PASS                                                                                                                                                                        |
| `composer audit --format=json`                        | No advisories or abandoned packages                                                                                                                                         |
| `npm audit --json`                                    | 0 vulnerabilities                                                                                                                                                           |
| Existing GitHub CI at `f5c6b0b`                       | Tests/linter SUCCESS; test log independently rechecked: 212 tests / 1,615 assertions                                                                                        |
| Public links                                          | Website, `/demo`, `/demo/replay` HTTPS GET 200 with expected ScopeTwin title and HSTS; GitHub anonymous API 200, public/main; YouTube oEmbed 200 with ScopeTwin video title |

Additional cleanup checks: all **79 local Markdown links/anchors across 15 documents passed**, including case-sensitive source targets; no active reference to a retired document remains outside this retirement record. All **286 non-documentation files** matched their pre-edit SHA-256 hashes, including CI, Composer/npm manifests and lockfiles, scripts and LICENSE. The 10 narration scenes and 30 caption cues are retained and valid; Python script syntax parses. Markdown formatting and `git diff --check` passed. The configured source/history secret audit scanned **307 source paths / 475 reachable Git blobs**, with **0 findings**; it checks current private literals and selected credential patterns, not an independent security assessment.

Full SQLite/PostgreSQL/browser suites, new image builds and media regeneration were not run locally for this documentation-only change. The [testing guide](TESTING.md) labels prior full-suite/live results by date and scope. A resolved video URL and available metadata do not imply a fresh full YouTube playback test.

### Application provenance and release baseline

ScopeTwin began on 18 October 2026 with the official Laravel React starter kit v1.0.1 and AI-assisted implementation of the original policy, workspace isolation, replay, approval and release workflows. Detailed development records and user-supplied prompts are preserved in Git history. The public Render release was verified on 21 October; [testing](TESTING.md) records the final application evidence. Separately authorized Backboard attempts did not produce accepted advice; production remains disabled and the published video contains no claimed AI success.

Remaining operating limits are the free-host/database lifetime, disabled email recovery, unverified live advice/cost and lack of independent certification. See [deployment](DEPLOYMENT.md), [Backboard boundaries](BACKBOARD_INTEGRATION.md) and [submission owner checks](SUBMISSION_CHECKLIST.md).
