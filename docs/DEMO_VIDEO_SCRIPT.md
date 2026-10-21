# ScopeTwin — verified 3:50 product demonstration

Completed on 2026-10-20: real browser footage, English offline narration and synchronized burned English captions. Final local video: `artifacts/submission-20261020/ScopeTwin-demo-1080p.mp4`. H.264, 1920×1080, 30 FPS, AAC stereo, 229.981042 seconds. Full decode and complete Chrome playback with audio passed. Published video: [ScopeTwin product demonstration](https://youtu.be/6cyv4wC1CR4). The recording uses local synthetic data; the current public HTTPS demo is linked in the README.

| Actual time       | Real screen/action                                                                                  |
| ----------------- | --------------------------------------------------------------------------------------------------- |
| 0:00–0:22         | Landing, product explanation                                                                        |
| 0:22–0:44         | Published ALLOW ≤50000 / REVIEW >50000 refund rules; integer USD cents                              |
| 0:44–1:10         | Three actual HTTP evaluations and recorded requests: $120 ALLOW, $2,400 REVIEW, unknown action DENY |
| 1:10–1:38         | Edit and save unpublished DENY draft for USD refunds >10000; Preview impact                         |
| 1:38–2:02         | Replay metrics: 3 samples, 2 changed, 2 newly denied; frozen policy context                         |
| 2:02–2:22         | Individual Original / Baseline / Candidate comparison and frozen evidence                           |
| 2:22–2:48         | Human confirmation: cancel, reopen, approve; original REVIEW and no execution preserved             |
| 2:48–3:07.705     | Actual authenticated audit JSON endpoint; no separate audit dashboard claimed                       |
| 3:07.705–3:32.325 | Real Usage & Plans counts; hypothetical pricing; factual usage/replay narration, no AI scene        |
| 3:32.325–3:49.981 | Product close and read-only tour                                                                    |

Narration source: `VIDEO_SCENES.json`. `DEMO_CAPTIONS.srt` is the final 30-cue English file, derived from measured WAV sentence durations. Full timing/evidence is in the ignored artifact directory. See [testing](TESTING.md) for dated application checks and limitations.

## Reproduction and safe recording

Reproduction is optional and creates a new synthetic workspace only on the isolated 18734 installation. It requires separate authorization if that installation is not yours. Keep Backboard disabled: no successful AI result is shown, and video recording is not permission for generation or credit spending.

1. Confirm the isolated built 18734 application is healthy and `BACKBOARD_ENABLED=false`. Leave 18731/18733 alone. Use the existing fixture for live-smoke work only; recording creates its own synthetic fixture.
2. Run Windows `powershell -NoProfile -File scripts/synthesize-demo.ps1` for offline Microsoft Zira narration, then `python scripts/assemble-demo-audio.py` for measured audio/timing/SRT. No paid speech service is used.
3. Run `node scripts/record-submission.mjs`. Registration and one-time key setup occur before recording in a separate browser context. Password, key and storage state remain in memory. The recorded context performs real UI/API operations; advice requests are blocked, never replaced with fake results.
4. Run `pwsh -NoProfile -File scripts/render-submission.ps1 -FfmpegDirectory <verified-local-ffmpeg-bin>`. The encoder validates duration/codec/dimensions/FPS, decodes every audio/video frame and checks audio before replacing the final file. An exclusive lock prevents overlapping renders. Tool binaries are private local dependencies, not source artifacts.
5. Run `node scripts/verify-video-playback.mjs` with installed Chrome. It serves the MP4 on an ephemeral loopback port, plays the whole file at 4× and requires `ended`, valid dimensions/duration, no media error and measured audio. Inspect encoded frames around policy/replay/approval/audit and captions too.
6. Deliver only the final MP4, optional SRT and submission materials. Keep raw footage private; do not publish environment files, cookies, key/assistant/thread identities or private provider responses. Verify any replacement public video link after upload.

No refund, payment, external tool execution, AI success or public HTTPS is implied by the recording. Real HTTP evaluations and documentary approval are supported by `recording-evidence.json`; this is a product demonstration, not a guarantee for arbitrary production workflows.
