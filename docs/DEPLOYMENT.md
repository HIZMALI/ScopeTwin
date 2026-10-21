# Deployment

The current [Render preview](https://scopetwin-hizmali.onrender.com) uses the public [GitHub repository](https://github.com/HIZMALI/ScopeTwin) and a separate PostgreSQL 18 database. See [dated verification](TESTING.md) and [preview configuration](#actual-render-preview---2026-10-21). The Compose instructions are a separate self-hosting option.

## Release design

One PHP 8.4 / FrankenPHP (Caddy, classic mode) image serves only `/app/public`, including the Node-built Vite assets. A separate PostgreSQL 18 container has **no published port**. The app runs as UID 33 on 8080 with dropped capabilities. Named volumes persist PostgreSQL and Laravel storage. Redis, workers and scheduler are unnecessary for the synchronous evaluation/replay/approval paths.

Docker stages install locked npm dependencies, build assets, install optimized production Composer dependencies and assemble the application. Base images are digest-pinned. No `.env`, Git metadata, host vendor tree, tests, screenshots or database dumps enter the build context. The boot script validates production/debug/URL/key settings and caches configuration/routes/views. **It never migrates, seeds, resets data or generates a key.** Runtime database privileges are DML and sequence usage, without superuser, schema creation or ownership; the migration-owner password is absent from the web environment.

The PostgreSQL image derives from the pinned official PostgreSQL 18 Alpine image and uses Alpine `su-exec` for privilege switching. Review the [Dockerfile](../Dockerfile), [database image](../deploy/postgres.Dockerfile) and current vulnerability scans before promoting a new image. Historical scans are dated in the [testing guide](TESTING.md); they are not a current remote-image certification.

## Reproduce the isolated local rehearsal (PowerShell 7)

```powershell
./scripts/rehearse-production.ps1
npm run test:release
./scripts/scan-containers.ps1
./scripts/test-database-image.ps1
node scripts/load-smoke.mjs
docker compose --env-file storage/.env.rehearsal -p scopetwin-build03 -f compose.production.yaml exec -T app php deploy/verify-runtime.php
docker compose --env-file storage/.env.rehearsal -p scopetwin-build03 -f compose.production.yaml exec -T app php deploy/benchmark.php --confirm-disposable
```

The opt-in script creates **fresh random secrets** in ignored `storage/.env.rehearsal`, new project `scopetwin-build03`, new database `scopetwin_build03_rehearsal`, and new volumes. It binds only `127.0.0.1:18733`; the user's 18731 / 55439 and existing Compose project are preserved. Repeated runs reuse the same private file and data. Never delete that file while retaining its database volume, or credentials will no longer match. Do not publish this HTTP exception to the Internet. Rehearsal cookies explicitly allow loopback HTTP; real HTTPS cookies must be Secure.

Open `/demo` for the account-free tour. The demo setup command runs the actual evaluator/replay/approval services and persists 3 requests, 2 changed outcomes, 2 newly denied outcomes and an APPROVED documentary review. It discards its random key and revokes the stored key. No shared login password exists. Other browser journeys create separate synthetic workspaces through real registration.

Only use the benchmark on this named disposable database. It adds 10,000 clearly synthetic decisions in separate fixture workspaces and compares a 500-item replay. It does not touch demo or user history. Do not describe its timings as a production capacity guarantee.

To stop the rehearsal without deleting evidence:

```powershell
docker compose --env-file storage/.env.rehearsal -p scopetwin-build03 -f compose.production.yaml stop
```

Never run `down -v` against an existing project. This release does not require deleting any volume.

## Self-hosted Linux deployment

1. Obtain explicit approval for the host, public repository/registry, domain, DNS and release. Verify the hackathon launch requirements. Provision a supported Docker host with adequate disk/memory, patched OS and firewall; expose only SSH to operators and HTTPS/HTTP to the TLS proxy. Keep the application's upstream on loopback.
2. Place a reviewed checkout/image on that host. Pin the verified image digest for promotion; moving image tags and external base images should be rescanned/rebuilt before each release. A clean source build uses `docker build --pull -t scopetwin:release .`. Review the container vulnerability evidence and resolve release-blocking findings before making it public.
3. Copy `.env.production.example` to an operator-owned **outside-repository** file such as `/etc/scopetwin/release.env`; `chmod 600` it. Generate a new random 32-byte base64 `APP_KEY`, separate random owner/runtime DB passwords, set `APP_URL=https://approved-host`, `RELEASE_DATABASE=scopetwin_release`, and `SESSION_SECURE_COOKIE=true`. Keep APP_KEY stable after launch: agent hashes depend on it. Never copy this machine's application/rehearsal secrets. Compose uses `--env-file`; do not print expanded `docker compose config` (use `config --quiet`).
4. Configure an independently managed TLS reverse proxy (Nginx/Caddy) on the approved domain. Point it to `127.0.0.1:18733`, overwrite forwarded headers, and configure `TRUSTED_PROXIES` to the **exact upstream source address/CIDR** after observing that network path. Default is trust nobody; never use `*`. The app accepts only the APP_URL host in production. Verify spoofed Host/forwarded headers are rejected/ignored. Enable `HSTS_ENABLED=true` only after external HTTPS and renewal work; it does not include subdomains or preload.
5. Before an existing release upgrade, create and verify a backup using the runbook. Inspect `php artisan migrate --pretend` with the migration identity. Only additive forward migrations are permitted against existing user data.
6. Start the release database, run migrations explicitly with the owner identity in a one-off container, then grant the runtime role. Example command structure follows. Supply passwords through private environment variables, never literal shell arguments. The web service is started **after** schema and runtime grants are complete.

```sh
# Linux; $RELEASE_ENV is an absolute private file path, never committed.

export RELEASE_ENV=/etc/scopetwin/release.env
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml config --quiet
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml build app database
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml up -d --wait database
# Set DB_PASSWORD privately to the migration-owner password for this one command.

docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml run --rm --no-deps --entrypoint php -e DB_USERNAME=scopetwin_owner -e DB_PASSWORD app artisan migrate --pretend
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml run --rm --no-deps --entrypoint php -e DB_USERNAME=scopetwin_owner -e DB_PASSWORD app artisan migrate --force --no-interaction
unset DB_PASSWORD
```

Grant `scopetwin_runtime` LOGIN with the private runtime password; CONNECT on the release DB, USAGE on public, SELECT/INSERT/UPDATE/DELETE on existing and future application tables, and USAGE/SELECT on existing and future sequences. Do **not** grant CREATE, SUPERUSER, CREATEDB, CREATEROLE or ownership. `scripts/rehearse-production.ps1` contains the exact tested SQL; its target guards deliberately prevent using it for a live deployment. Provision live credentials through the operator's secure SQL channel. The official Postgres image's bootstrap owner is privileged; only the operator may use it. Do not expose it to the web process.

```sh
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml up -d --wait app
docker compose --env-file "$RELEASE_ENV" -p scopetwin-release -f compose.production.yaml exec -T app php deploy/verify-runtime.php
```

PowerShell equivalent: set `$releaseEnv='C:/private/release.env'`, use `--env-file $releaseEnv`, and assign/remove `$env:DB_PASSWORD` inside a `try/finally` block as the rehearsal script does. Do not echo the variable or expanded Compose settings.

## Optional public judge installation

Use a **separate fresh** project/database named e.g. `scopetwin_public_demo`; never seed a real user database. Set `DEMO_ENABLED=true`, temporarily `DEMO_ALLOW_SETUP=true`, migrate/grant, and run:

```sh
docker compose --env-file "$RELEASE_ENV" -p scopetwin-public-demo -f compose.production.yaml run --rm --no-deps --entrypoint php app artisan scope:judge-demo --confirm-disposable
```

First setup requires an empty database. The command also requires the dedicated `scopetwin_*demo` / `*_rehearsal` name; `scopetwin` is always refused. Its fixed workspace ID/slug must match; an inconsistent identity fails without edits. Repeated setup preserves existing artifacts. Disable `DEMO_ALLOW_SETUP` afterward and recreate app to refresh cached configuration. Public `/demo` and `/demo/replay` are stateless GET/HEAD routes with no session, registration, keys or write controls; unknown IDs and mutations fail. Visitors cannot select a tenant via URL/query/body. Other authenticated workspaces are never included in its capped projections.

## Verify before public launch

- `/up`: liveness; `/ready`: migrated DB/session/cache connectivity, generic ready/unavailable only. Remove an unhealthy app from proxy rotation. No background worker is required.
- Test login, session renewal, real ALLOW/REVIEW/DENY, retry/conflict, replay, documentary approval and usage on a **dedicated external smoke workspace**, with explicit operator authorization.
- Check HTTPS/redirects, Secure/HttpOnly/SameSite cookies, CSP in the browser, exact proxy/host trust, `.env`/`.git`/storage denial and generic error pages. Local HTTP rehearsal cannot establish real TLS, HSTS or SMTP delivery.
- Mail recovery is disabled by default. Use a verified SMTP transport, configure sender/domain authentication, enable `PASSWORD_RECOVERY_ENABLED=true`, restart and actually test delivery before advertising recovery. Array/log mailers never count as delivery. Registration is 5/minute/IP; login retains the starter's 5-attempt email/IP limiter.
- Never publish a raw API key, environment file, trace or database dump. Judge images contain synthetic data only. Verify public URLs and update release materials only after checking the actual endpoints.

For a real TLS reverse proxy, review both [FrankenPHP proxy configuration](https://frankenphp.dev/docs/production/#running-behind-a-reverse-proxy) and Laravel's explicit trusted proxies. Configure Caddy's `servers { trusted_proxies static <actual addresses> }` only for the approved proxy path, and verify spoofing/secure detection externally; no wildcard trust is shipped. The checked-in Caddyfile keeps internal automatic HTTPS/admin off, serves only public/ and blocks internal/dot paths. Caddy's configuration/data directories are writable by the non-root user; internal TLS certificates are not generated in this setup.

References: [Laravel 13 deployment](https://laravel.com/docs/13.x/deployment), [FrankenPHP Docker](https://frankenphp.dev/docs/docker/), reviewed 2026-10-18. The self-hosted steps above are procedures; actual managed-host configuration follows below. Verification is recorded in [testing](TESTING.md).

## Actual Render preview - 2026-10-21

| Setting      | Deployed configuration                                                                                              |
| ------------ | ------------------------------------------------------------------------------------------------------------------- |
| Web          | Free Docker service, Oregon, 512 MB / 0.1 CPU, port 8080, health `/ready`, managed onrender.com HTTPS               |
| Database     | Separate Free PostgreSQL 18, `scopetwin_public_demo`, 1 GB, autoscaling disabled                                    |
| Updates      | Manual deploy from reviewed Git `main`; a repository push alone is not a deployment                                 |
| Secrets      | New private application key/runtime password; no local environment copied; migration-owner password absent from web |
| Runtime role | `scopetwin_runtime`: DML/sequence usage, no superuser, database/role/schema creation or ownership                   |
| Public tour  | Guarded synthetic fixture installed only in this new database; `DEMO_ALLOW_SETUP=false` afterward                   |
| Features     | `BACKBOARD_ENABLED=false`, no Backboard credential remotely; `MAIL_MAILER=array`, `PASSWORD_RECOVERY_ENABLED=false` |

Configuration uses production/debug-off operation, canonical HTTPS APP_URL, database sessions/cache and encrypted Secure/HttpOnly/SameSite=Lax cookies. Logs use stderr with production redaction. The web filesystem is ephemeral; no user file-upload workflow requires a persistent disk.

**Proxy trust is deployment-specific.** The verified external network path reaches PHP via IPv6 loopback `::1`, while health checks use separate private IPv4. `TRUSTED_PROXIES=::1`, `RELEASE_OBSERVE_PROXY=false`, and HSTS is enabled. Forwarded host/port/prefix are ignored. On this verified Render/Cloudflare path only, `TRUSTED_CLIENT_IP_HEADER=CF-Connecting-IP` accepts the edge-overwritten single client IP from a trusted peer. Raw visitor XFF chains cannot choose rate-limit identities; missing/invalid edge IP falls back to the actual peer. This opt-in defaults empty elsewhere. Do not copy these trust settings to another topology without verification.

The free service lacks SSH/shell/pre-deploy jobs. Approved provisioning used a private operator connection with TLS `verify-full` and trusted CA; web uses the internal database hostname with `DB_SSLMODE=require` because that internal certificate does not support `verify-full`. Resource-specific external database access is now closed. Future operator migrations require an approved temporary narrow access rule and closure afterward. Preserve APP_KEY and use explicit forward migrations; never reset the database or put owner credentials into the web service.

The free web service may sleep after 15 idle minutes, with a platform-advertised wake time around a minute. An actual provider suspension/cold boot was not established by the recorded quiet-client check. Free PostgreSQL is scheduled to expire on **19 November 2026**, without managed backups/PITR. Arrange a private export and continued hosting before expiry if needed. Shared workspace quotas may also suspend services or builds. This preview is not a commercial SLA; paid upgrades require separate approval.

Official references: [Render Laravel Docker](https://render.com/docs/deploy-php-laravel-docker), [free constraints](https://render.com/docs/free), [PostgreSQL](https://render.com/docs/postgresql-creating-connecting), [inbound access](https://render.com/docs/inbound-ip-rules). These describe platform procedures; [test evidence](TESTING.md) records what was actually verified.
