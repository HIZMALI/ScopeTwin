# Public release gates — 2026-10-21

Current public source: [HIZMALI/ScopeTwin](https://github.com/HIZMALI/ScopeTwin). Live HTTPS: [ScopeTwin](https://scopetwin-hizmali.onrender.com), [account-free demo](https://scopetwin-hizmali.onrender.com/demo). The owner explicitly authorized this free Render release. [Current technical evidence](PUBLIC_RELEASE_REPORT.md) supersedes the dated historical BUILD03 sections below.

- [x] Public repository, no force push, actual remote CI test/linter success.
- [x] New Free PostgreSQL18, forward migrations, guarded real synthetic demo, DML-only web role, new private secrets.
- [x] Actual external registration/login,agent,policy publication,ALLOW/REVIEW/DENY,replay2/2,approval,audit,usage,tenant isolation.
- [x] External Chromium desktop/mobile,axe,keyboard,CSP; combined3/3 PASS.
- [x] Verified HTTPS,redirect,HSTS,cookies,Host/private path/CSRF/demo boundaries,spoof-resistant rate limit.
- [x] External DB access closed; actual owner connection rejected, internal app healthy.
- [x] Backboard and SMTP/recovery disabled; no generation/purchase/video/Devpost action.
- [ ] Natural free-tier sleep/wake timing — recorded separately in the current release report.
- [ ] Owner video upload,eligibility review,Devpost submission.
- [ ] Long-term persistence/SLA/backup/SMTP require a separately approved operating plan. Free DB expires November19,2026.

The sections below are preserved as historical BUILD03 evidence; their old NOT DEPLOYED/NOT AUTHORIZED labels are not the current remote state.

---

# Historical BUILD 03 release gates

Latest urgent fix: see BACKBOARD_FIX_REPORT.md. Separate one-call authorization yielded HTTP200/statusFAILED upstream; no AI success. Free credit scope is memory/RAG-only and paid LLM credit unavailable. Owner selected the revised verified MP4 without an AI scene; no further generation/purchase authorized. Prior dated build rows are historical.

**Local release candidate only. Remote state: NOT DEPLOYED.**

Latest delivery, 2026-10-20: the 3:50 real-browser MP4 is complete and verified (1080p/30 FPS/H.264/AAC/English narration and captions). One authorized live Backboard attempt failed output acceptance; cleanup confirmed, flag restored false. The owner will upload video and submit Devpost. See SUBMISSION_DELIVERY_REPORT.md; older build/test rows below are dated historical evidence, not current success claims. A checked local gate is not evidence of a public service, submitted project or completed video. Final command counts/timestamps and any exceptions are in BUILD03_TECHNICAL_NOTES.md.

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

BUILD 03.6 approved follow-up, 2026-10-18: one dedicated assistant was created after an empty exact-name lookup; tools/documents/threads/memories are empty. Only 18734 was privately bound to it and `featherless` / `openai/gpt-oss-20b`, then app-only recreated with the verified BUILD 03.6 image. It remains **disabled**. ACLs are private, Docker TLS verifies normally, 18731/18733 and original data are preserved. Fresh evidence: SQLite/PostgreSQL 178 tests / 1367 assertions each, all concurrency checks, four disposable Chromium workflows, three production public-tour browsers and running-image Trivy HIGH/CRITICAL zero. **At that dated setup: Backboard live call NOT TESTED.** See the authorized follow-up in BUILD03_6_CONNECTIVITY_REPORT.md; older build rows remain historical.

2026-10-20 recheck: the same assistant, empty context, private cached binding, model/JSON/prices and disabled 18734 BUILD 03.6 runtime were reverified without creating another assistant or changing private files. Docker was initially off and was started only after explicit user approval; 18733/18734 automatically resumed healthy. The initially stopped 18731 host app/original development PostgreSQL were left stopped. Do not reuse the 2026-10-18 all-three-services health statement as current evidence. Fresh tests and this availability limitation are recorded in the report's dated recheck section. That dated recheck made no generation request; the later single authorized attempt and its unsuccessful result are recorded in SUBMISSION_DELIVERY_REPORT.md.

BUILD 03.5 local addendum: 169 PHP tests / 1291 assertions on both SQLite/PostgreSQL, three advisor concurrency races, four Chromium regression scenarios and six three-browser release scenarios passed. Separate 18734 package, two additive advisor tables, quota/cache/cleanup and disabled fallback are implemented. Existing 183 PostgreSQL domain rows and .env are unchanged. Provider remains LIVE_NOT_TESTED/MODEL_NOT_SELECTED. Exact fresh evidence and initial local-environment limitation are in BUILD03_5_BACKBOARD_REPORT.md; BUILD 03 rows above describe the historical baseline.

- [ ] **NOT TESTED / NOT AUTHORIZED:** select/approve hosting, operating costs, domain and DNS changes. No host was bought or exposed remotely.
- [ ] **NOT DEPLOYED:** configure the approved TLS proxy, exact trusted addresses, real HTTPS/cookies/Host checks and certificate renewal. Then verify the public URL from outside the host without an existing session.
- [ ] **NOT TESTED:** configure and verify real SMTP delivery before enabling recovery. Current array mailer is honestly unavailable for password recovery.
- [ ] **NOT PUBLISHED:** source/history secret scan passed; user authorized publication but no GitHub destination was supplied. No remote was created or changed, no push performed.
- [ ] **NOT VERIFIED:** owner Devpost/team participation, Discord membership and partner-credit redemption/eligibility. Confirm organizer interpretation; no promo code, API key or partner account action is automated.
- [ ] **LIVE_ATTEMPT_FAILED / DISABLED:** one authorized generation on 2026-10-20 produced no accepted explanation. Thread cleanup confirmed, no retry, billing unverified. Configured isolated assistant/model remain private. A new attempt requires separate permission; no live AI success may be advertised.
- [x] **LOCAL MP4 VERIFIED:** actual 3:50 1920×1080 30 FPS H.264/AAC video with English narration and captions. Full decode and complete Chrome playback/audio passed. Upload is owner-owned and remains pending; `[VIDEO_URL]` remains a placeholder.
- [ ] **NOT SUBMITTED:** replace `[LIVE_DEMO_URL]`, `[PUBLIC_GITHUB_URL]`, `[VIDEO_URL]` only with verified real URLs, review the Devpost text and submit through the owner's account.
- [ ] **NOT RUN REMOTELY:** GitHub-hosted CI, independent penetration test, formal accessibility/compliance certification and production capacity/restore RPO/RTO validation.

## Exact next release instruction

After reviewing local `/demo` and the technical evidence, authorize a named hosting target, domain/TLS/DNS plan and public GitHub destination. Use DEPLOYMENT.md to build/scan/promote the reviewed images, take a backup, migrate forward with the separate owner identity and verify external HTTPS. Keep the synthetic judge installation separate. The actual video is now complete and inspected; finish the owner-only upload, eligibility and submission steps. Until then, leave all external URLs as placeholders and the release marked NOT DEPLOYED.
