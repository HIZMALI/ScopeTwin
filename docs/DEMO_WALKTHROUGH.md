# Read-only demo walkthrough

Open the live [ScopeTwin demo](https://scopetwin-hizmali.onrender.com/demo). No account or secret is required. The public tour reads persisted synthetic artifacts; it does not run API evaluations or mutate evidence when visited.

1. **Recorded requests:** inspect the stored $120 ALLOW, $2,400 REVIEW and unknown-action DEFAULT_DENY examples. Bearer credentials are omitted.
2. **Replay Lab:** open [the comparison](https://scopetwin-hizmali.onrender.com/demo/replay). An unpublished DENY rule above USD $100 yields **2 changed / 2 newly denied**. Original, Baseline and Candidate remain distinct; expand the evidence fingerprints.
3. **Human review:** the recorded REVIEW remains REVIEW. Stored APPROVED status documents a human choice; execution performed is false. There is no public approval button.
4. **Workspace workflow:** the [video](https://youtu.be/6cyv4wC1CR4) shows authenticated agent/key management, policy publication, real API evaluations, replay, human review, the scoped audit JSON endpoint and Usage & Plans. A separate audit dashboard is not claimed.

To try your own data, register a workspace through the application. Keep issued keys private; no shared demo login exists. Public tour query/UUID inputs cannot select another workspace, and public mutations are rejected. Backboard advice and email recovery remain disabled in production.

For a local isolated tour, follow [deployment rehearsal instructions](DEPLOYMENT.md#reproduce-the-isolated-local-rehearsal-powershell-7). The ordinary development database is never automatically seeded. Recorded release coverage is in [testing](TESTING.md).

## Screenshots

These are actual local browser captures of synthetic application data, not evidence of real financial outcomes or a successful AI response.

| View    | Desktop                                  | Mobile                                  |
| ------- | ---------------------------------------- | --------------------------------------- |
| Landing | [Screenshot](images/landing-desktop.png) | [Screenshot](images/landing-mobile.png) |
| Demo    | [Screenshot](images/demo-desktop.png)    | [Screenshot](images/demo-mobile.png)    |
