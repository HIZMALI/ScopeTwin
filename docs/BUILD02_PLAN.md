# BUILD 02 — queue for next Codex run (NOT YET IMPLEMENTED)

Run only after Build 01 is demonstrated. Accelerated feature gate:
1. Replay Lab: select historical action requests + compare active published snapshot vs draft; show decisions changed (ALLOW/REVIEW/DENY) and false-block/false-allow indicators with clearly defined labels. Replay must be side-effect free, tenant scoped and deterministic.
2. Approval queue: explicit pending approval on REVIEW, safe resolution by owner/admin, decision audit; no external payment or other side effects.
3. Usage dashboard + pricing screen: metered evaluations and subscription-plan hypothesis; if checkout is not live, prominently label sandbox/preview. No fake paid transactions.
4. Public demo landing page, safe seeded judge/demo tenant, narrated real HTTP demo path; avoid globally sharing a writable admin account with confidential data.
5. Critical security suites + Playwright journeys, responsive/mobile review and performance checks.
6. Optional Backboard AI explanation adapter: isolated behind feature flag, never authorizes an operation, handles unavailable credentials gracefully.
7. Build 03: public cloud deployment, security hardening, GitHub README/docs, actual 2–5 min video, Devpost description and links; do not claim outside actions have happened until user confirms.
