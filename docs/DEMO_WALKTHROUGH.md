# Read-only judge tour

Remote status: **NOT DEPLOYED**. Local rehearsal: [http://127.0.0.1:18733/demo](http://127.0.0.1:18733/demo). Start it with `./scripts/rehearse-production.ps1`; no secret or login is needed for the tour. The ordinary development installation at 18731 honestly shows demo unavailable unless separately configured; its user database is never seeded.

1. Landing → **Try read-only demo**. The page is explicitly labelled **Synthetic demo data** and reads persisted artifacts from a fixed designated workspace.
2. **Recorded requests**: inspect the actual $120 ALLOW and $2,400 REVIEW scenarios. The unknown action is DEFAULT_DENY. Request examples omit bearer credentials; the result is a projection of stored evidence.
3. **Compare the draft**: an unpublished DENY rule above USD $100 produces exactly **2 changed / 2 newly denied** compared with the frozen current baseline. Compare recorded, baseline and candidate independently. Expand evidence fingerprints.
4. **Human review**: the recorded REVIEW remains REVIEW; an operator's stored APPROVED status documents a choice. Execution performed is **False**. There is no approval button in this public tour.
5. **Create workspace** enters the real authenticated product: agent keys, policy publishing, live HTTP evaluations, Replay Lab, approvals, actual Usage & Plans. Never show a raw issued key in a recording; acknowledge/save it before continuing.

The public surface accepts only GET/HEAD at `/`, `/demo`, `/demo/replay` and `/ready` (plus framework liveness `/up`). It has no session middleware or public mutation handler. Demo queries ignore tenant/UUID/filter inputs, restrict the fixed ID and slug, cap displayed results and project safe fields only. Authenticated routes remain protected by their original roles, session/CSRF and agent keys. Setup is CLI-only, opt-in and idempotent, with an explicit disposable database guard.

Screenshots under `docs/images/` are actual browser captures from synthetic demo/test environments, with no visible bearer token or password. Their capture evidence and browser coverage are in BUILD03_TECHNICAL_NOTES. They are product evidence, not proof of a remote service or real financial outcomes.
