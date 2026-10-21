# ScopeTwin operations runbook

## Responsibilities and invariants

The operator owns host patches, TLS/DNS, backups, key custody, SMTP and restore drills. The application owns identity-derived scope, deterministic evaluation, transactional idempotency, frozen replay and freshness-checked documentary approvals. No action executor, payment processor, queue worker or scheduler is needed.

Never regenerate an existing APP_KEY: agent key hashes use it. Back up the key separately in an approved encrypted secret store; do not copy it into this repository or routine command output. Protect both migration and runtime passwords; the web container receives only its runtime password. Direct database owners can alter historical evidence: application immutability is not database RLS, a trigger, WORM storage or cryptographic attestation.

## Managed preview

The current Render configuration and free database expiry are in [deployment](DEPLOYMENT.md#actual-render-preview---2026-10-21). External database access is closed and web uses only the restricted runtime identity. A future migration or backup requires an approved private operator connection, temporary narrow access if necessary, and closure afterward. Never place the migration-owner password into the web environment. Backboard and email recovery stay disabled until separately configured and verified.

The Compose commands below apply to self-hosted/local rehearsal installations, not the managed Render service.

## Daily health and logs

Use the appropriate explicit Compose file, private env file and project name on **every** command. The local rehearsal uses `scopetwin-build03`; the existing development app uses its existing project. Do not substitute one for the other.

```powershell
docker compose --env-file storage/.env.rehearsal -p scopetwin-build03 -f compose.production.yaml ps
Invoke-RestMethod http://127.0.0.1:18733/ready
docker compose --env-file storage/.env.rehearsal -p scopetwin-build03 -f compose.production.yaml logs --tail=100 app
```

Production structured exceptions retain type/level/time rather than SQL bindings, headers, messages or traces, intentionally sacrificing detail to avoid PII/credential leakage. Audit events contain finite event types, decision IDs and non-sensitive metadata. Caddy access logging is disabled to avoid recording user-supplied query strings. Monitor availability/latency at the trusted reverse proxy with a reviewed redaction policy; never log Authorization or cookies. Inspect relevant audit identifiers using a scoped operator workflow. Debug must stay off during incidents.

## Back up before schema changes

Use PostgreSQL custom-format `pg_dump`, include schema/migrations/domain/session data, and separately preserve application storage and release image digest. Encrypt backups outside the checkout, restrict permissions, rotate by the agreed retention policy, and test restoration. DB backups contain credentials/hashes and PII even though public screenshots do not. Do not attach them to chat, Git, CI artifacts or Devpost.

Linux example, after target approval:

```sh
umask 077
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml exec -T database pg_dump -U scopetwin_owner -d scopetwin_release -Fc > /approved-private-backups/scopetwin-before-release.dump
# Check listing; this alone is not a successful restore drill.

docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml exec -T database pg_restore --list < /approved-private-backups/scopetwin-before-release.dump
```

PowerShell 7.4+ supports native binary redirection. On older Windows hosts use `docker compose exec database pg_dump ... -f /tmp/release.dump`, then `docker compose cp database:/tmp/release.dump C:/approved-private-backups/release.dump`; verify the absolute output target and permissions. Never route binary backup bytes through `Get-Content`/text encoding.

## Restoration

Restore only into a **new isolated empty** database/project first. Never use `--clean`, overwrite a volume, `db:wipe`, `migrate:fresh` or rollback against `scopetwin` or a live release. Create the new target under operator approval, restore with the migration owner, grant the separate runtime identity again, load the corresponding existing application key privately, then run readiness, row counts/hash sampling, login/API/replay/approval checks and credential validation. Compare backup timestamps and schema versions. A backup without the matching key cannot validate existing agent tokens.

Only switch the approved proxy target after the restore drill passes and the owner authorizes recovery. Preserve the previous database for investigation. Define RPO/RTO with the owner; no RPO/RTO guarantee is claimed by this hackathon build. The historical isolated backup/restore result is recorded in [testing](TESTING.md#production-packaging-and-recovery-checks); it does not establish a backup of the live preview.

## Release / rollback / restart

1. Freeze release content, validate dependencies and container advisories, take a verified backup and record current image/schema versions.
2. Run reviewed additive forward migrations explicitly using the one-off migration identity. Booting or restarting app never performs DDL.
3. Promote the reviewed image, `up -d --wait app`, verify built assets/readiness and a dedicated smoke workspace. Config/env changes require app recreation because configuration is cached at boot.
4. If application behavior fails, point back to the previous compatible image; preserve the additive schema. Do not automatically run migration rollback. An incompatible schema requires a reviewed forward repair or an explicitly authorized restore into a new target. Rehearsal uses the same principles.

## Approval expiry and evidence retention

Pending reviews expire at 24 hours (`expires_at <= now`). Policy set changes, originating-key revocation and agent disablement invalidate them. GET derives EXPIRED/STALE without writes; attempted resolution persists the terminal invalid state and returns 409. No scheduler is required to enforce invalidity. Approval never executes an operation and never changes the original REVIEW.

No automatic audit/decision/replay deletion is installed. Keep evidence until the owner establishes retention/legal requirements and an approved export/backup process. Replay is bounded to latest 100/default, 500/maximum and 1 MiB manifests. Large history may need archival/pagination/index review; never truncate to fix performance. Database session/cache garbage collection is framework infrastructure, not evidence retention. Log rotation and encrypted backup retention are operator responsibilities.

## Incident actions

- Suspected leaked key: revoke that key through its owning workspace; inspect decision/audit IDs; rotate an agent key without rotating APP_KEY. Revocation invalidates pending approvals originating from it.
- Tenant authorization concern: restrict ingress at the proxy, preserve evidence/backups, reproduce in an isolated database, repair and rerun both database suites plus browser/security checks before reopening.
- DB unavailable: readiness returns generic 503; keep liveness separate, inspect private DB connectivity/disk/roles, recover without resetting volume.
- CSP/UI issue: reproduce against built assets and examine browser policy violations. Do not globally disable CSP to conceal a failed release.
- Mail failure: disable recovery, show honest unavailable state and restore verified delivery before re-enabling it.
- Certificate renewal: configure the chosen proxy's renewal, expiry monitoring and external HTTPS checks. Validate a dry-run renewal using that provider's documented process after authorization. External HTTPS was verified on the Render preview; an actual certificate renewal drill remains unverified.
