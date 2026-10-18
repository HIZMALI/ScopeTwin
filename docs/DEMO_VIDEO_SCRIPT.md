# ScopeTwin — 3:15 product demonstration

Status: **storyboard and captions draft; no completed MP4/public video claimed**. All frames must be captured from the running isolated synthetic environment. Remote URLs remain placeholders until release authorization. Narration below is English; the SRT draft supports a silent-captioned edit.

| Time | Actual screen/action | Narration / on-screen meaning |
| --- | --- | --- |
| 0:00–0:20 | Landing at 18733; open read-only demo, synthetic label visible | “An agent asks to issue a refund. A policy decides whether it should proceed. But before changing that policy, how do we know which requests would change? ScopeTwin makes that impact visible.” |
| 0:20–0:40 | Dedicated synthetic authenticated workspace; published refund ALLOW ≤50000 / REVIEW >50000 | “These are typed, published rules. Amounts are integer cents. The default is deny, and a matching deny always wins.” |
| 0:40–1:00 | Actual HTTP API requests/results: 12000 ALLOW, 240000 REVIEW, unknown.action DENY; show decision log | “The real API records three outcomes: allow $120, review $2,400, deny an unknown action. Each has a reason and an immutable policy reference. No refund is executed.” |
| 1:00–1:25 | Save DENY draft for refund.issue, USD, amount >10000. Click Preview impact | “We propose a stricter $100 boundary. Saving a draft does not publish it. Preview impact runs the same deterministic engine over recorded inputs.” |
| 1:25–1:55 | Replay report metrics and three outcome columns; inspect both changed rows | “Three separate facts: what was recorded, what today's published baseline would decide, and what the candidate would decide. Exactly two labels change to deny; the unknown action stays denied.” |
| 1:55–2:15 | Expand frozen definitions/fingerprints; show draft still unpublished and decision history unchanged | “This report freezes the inputs and policy definitions. Later edits cannot rewrite it. Two changes are measured label changes, not invented financial savings. Replay calls no external tools.” |
| 2:15–2:45 | Pending review detail; open confirmation, cancel once, reopen/confirm; show APPROVED + original REVIEW | “A human can document approval while the policy, key and time context remain valid. Changes or expiry block stale approval. Approval records a choice; it does not execute a refund or rewrite REVIEW.” |
| 2:45–3:00 | Architecture section; real Usage & Plans | “One Laravel and React application with PostgreSQL. Scoped identity, hashed keys, transactional idempotency and tested concurrency. Usage comes from real records.” |
| 3:00–3:15 | Proposed pricing disclosure; final landing/demo CTA | “Free, Team $79 and Business $299 are pricing hypotheses, with no charges. Try the read-only synthetic tour. Evaluate. Replay. Review. Execution stays with you.” |

## Safe capture procedure

BUILD 03.6: **Backboard live call: NOT TESTED**. Do not add a fabricated AI result to this video. The source adapter requires explicit dedicated assistant binding. An approved dedicated empty assistant, catalog model and private disabled 18734 binding are now verified; these and mock fixtures are engineering evidence only. A recorded live explanation requires separate authorization for a synthetic generation, its implicit thread creation and cleanup, then an actual successful result and safe capture without keys or assistant/thread identifiers. The deterministic walkthrough above remains provider-independent.

1. Run the rehearsal and full browser tests. Use a new synthetic workspace and generated in-memory password. Never record an existing user account or database.
2. Create/save the agent key **before starting screen capture**; keep it in a private process variable. Do not put it in a terminal command, source file, screenshot or video. Use `scripts/demo-api.ps1` with a hidden prompt for live requests; only capture the sanitized response. Do not show environment files, browser storage, session cookies, network Authorization headers or devtools containing credentials.
3. Perform the actual UI/API actions in the table. Screen recording must capture those actions, not simulated cursor animation. Keep output labels and timing consistent with the real state. Use 1440×1000 or 1920×1080 desktop recording; use the verified mobile screenshot only as an explicitly separate view.
4. Windows: record the browser window with an available local screen recorder. FFmpeg was checked separately in the release evidence; do not claim it exists if unavailable. With an installed/approved FFmpeg, a Windows desktop input example is `ffmpeg -f gdigrab -framerate 30 -i desktop -t 195 -c:v libx264 -pix_fmt yuv420p private-capture.mp4` (captures the entire desktop, so clear all other windows/secrets first). Linux uses the installed compositor's recording interface; do not guess an X11/Wayland display.
5. Edit real footage to 3:15, add `DEMO_CAPTIONS.srt`, inspect every frame around agent setup/API/approvals, and play the final MP4 from start to end. Verify file duration and audio with `ffprobe` if available. Keep raw footage private. Only then upload under explicit authorization and replace `[VIDEO_URL]`.

No final screencast, voiceover or remote video upload is part of the verified local artifact unless a real playable file is listed in BUILD03_TECHNICAL_NOTES. Actual safe screenshots are supplied instead.
