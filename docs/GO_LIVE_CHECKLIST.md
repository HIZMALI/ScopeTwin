# BUILD 03 release gates

**Local release candidate only. Remote state: NOT DEPLOYED.** A checked local gate is not evidence of a public service, submitted project or completed video. Final command counts/timestamps and any exceptions are in BUILD03_TECHNICAL_NOTES.md.

## Implemented local gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Original 126 tests retained; expanded SQLite / PostgreSQL suite | PASS | 142 tests / 1031 assertions on each engine |
| Original three Chromium journeys retained | PASS | Existing Build 01, replay and approval specs; 3 passed |
| Built-artifact production-mode journey | PASS | Isolated 18733 + new PostgreSQL service; no Vite dev server |
| Chromium / Firefox / WebKit desktop/mobile | PASS with scoped limitation | 6 release scenarios; WebKit Windows link Tab traversal unavailable, programmatic skip focus checked |
| axe / dialog / focus / overflow | PASS for tested pages | 1440/390px, landing, tour, overview, policy list/editor, replay, approvals, usage; no certification |
| API contract / idempotency / concurrency / scope | PASS | Regression, real HTTP retry/conflict and PostgreSQL races |
| Read-only synthetic judge tour | PASS | Fixed workspace, persisted 3-way comparison, no cookies/GET writes, no secrets/public mutations |
| Private files / forged Host / CSRF / secure runtime | PASS | 404 private paths, 400 forged host, 419 cross-site mutation; debug off/non-root/DML-only DB |
| Reproducible image / startup guards | PASS | Digest-pinned inputs, patched server dependency, explicit migration, no auto key/seed/reset |
| Backups and operations | PASS locally | Fresh isolated restore: all 13 domain table counts/hashes equal; live recovery remains operator responsibility |
| Environment and original data preservation | PASS | Both guards: .env and 183 original domain rows unchanged; original ports retained |
| Source/format/type/build/audits | PASS | Exact commands/results in technical notes; secret pattern scan has defined coverage |
| Application and database container security gate | PASS | Final app and PostgreSQL images: 0 HIGH / 0 CRITICAL in Trivy; no CVE suppression/ignore |
| Devpost / runbooks / screenshots / captions | PASS | English drafts and actual anonymous screenshots, Turkish technical report |

## External launch blockers — do not check without real evidence

BUILD 03.5 local addendum: 169 PHP tests / 1291 assertions on both SQLite/PostgreSQL, three advisor concurrency races, four Chromium regression scenarios and six three-browser release scenarios passed. Separate 18734 package, two additive advisor tables, quota/cache/cleanup and disabled fallback are implemented. Existing 183 PostgreSQL domain rows and .env are unchanged. Provider remains LIVE_NOT_TESTED/MODEL_NOT_SELECTED. Exact fresh evidence and initial local-environment limitation are in BUILD03_5_BACKBOARD_REPORT.md; BUILD 03 rows above describe the historical baseline.

- [ ] **NOT TESTED / NOT AUTHORIZED:** select/approve hosting, operating costs, domain and DNS changes. No host was bought or exposed remotely.
- [ ] **NOT DEPLOYED:** configure the approved TLS proxy, exact trusted addresses, real HTTPS/cookies/Host checks and certificate renewal. Then verify the public URL from outside the host without an existing session.
- [ ] **NOT TESTED:** configure and verify real SMTP delivery before enabling recovery. Current array mailer is honestly unavailable for password recovery.
- [ ] **NOT PUBLISHED:** owner approval for GitHub public repository/remote/push, license/provenance review and a final secret scan. No remote was created or changed.
- [ ] **NOT VERIFIED:** owner Devpost/team participation, Discord membership and partner-credit redemption/eligibility. Confirm organizer interpretation; no promo code, API key or partner account action is automated.
- [ ] **LIVE_NOT_TESTED / MODEL_NOT_SELECTED:** BUILD 03.5 optional Backboard adapter is implemented with mocked tests and disabled defaults. Privately verify model/JSON support/prices, assistant context and balance, obtain explicit spend authorization, then run the synthetic one-call smoke and verify cleanup. No real provider response or partner-promotion eligibility is claimed. See BACKBOARD_INTEGRATION.md and BUILD03_5_BACKBOARD_REPORT.md. Core evaluation/replay/review works without it.
- [ ] **NOT PRODUCED:** capture/edit/play a real 3:15 MP4, upload only after authorization, then check `[VIDEO_URL]`. Storyboard/SRT/screenshots are ready; they are not a finished video.
- [ ] **NOT SUBMITTED:** replace `[LIVE_DEMO_URL]`, `[PUBLIC_GITHUB_URL]`, `[VIDEO_URL]` only with verified real URLs, review the Devpost text and submit through the owner's account.
- [ ] **NOT RUN REMOTELY:** GitHub-hosted CI, independent penetration test, formal accessibility/compliance certification and production capacity/restore RPO/RTO validation.

## Exact next release instruction

After reviewing local `/demo` and the technical evidence, authorize a named hosting target, domain/TLS/DNS plan and public GitHub destination. Use DEPLOYMENT.md to build/scan/promote the reviewed images, take a backup, migrate forward with the separate owner identity and verify external HTTPS. Keep the synthetic judge installation separate. Record and inspect the actual video; finish the owner-only eligibility and submission steps. Until then, leave all external URLs as placeholders and the release marked NOT DEPLOYED.
