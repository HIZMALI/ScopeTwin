param([switch]$Build035)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
# This script never reads/copies .env or uses the existing Compose project.
$releaseTag = if ($Build035) { 'build035' } else { 'build03' }
$releasePort = if ($Build035) { '18734' } else { '18733' }
$releaseDatabase = "scopetwin_${releaseTag}_rehearsal"
$releaseRelativeFile = if ($Build035) { 'storage/.env.build035-rehearsal' } else { 'storage/.env.rehearsal' }
$releaseFile = Join-Path (Get-Location) $releaseRelativeFile
function New-ReleaseSecret { [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant() }
if (-not (Test-Path -LiteralPath $releaseFile)) {
    if (Get-NetTCPConnection -LocalPort $releasePort -State Listen -ErrorAction SilentlyContinue) { throw 'Rehearsal port is occupied; refusing to replace its owner.' }
    $releaseKey = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    @"
RELEASE_ENV_FILE=./$releaseRelativeFile
RELEASE_PORT=$releasePort
RELEASE_DATABASE=$releaseDatabase
APP_IMAGE=scopetwin:$releaseTag
DATABASE_IMAGE=scopetwin-postgres:$releaseTag
RELEASE_DB_OWNER_PASSWORD=$(New-ReleaseSecret)
APP_NAME=ScopeTwin
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$releaseKey
APP_URL=http://127.0.0.1:$releasePort
APP_ALLOW_INSECURE_HTTP=true
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=database
DB_PORT=5432
DB_DATABASE=$releaseDatabase
DB_USERNAME=scopetwin_runtime
DB_PASSWORD=$(New-ReleaseSecret)
SESSION_DRIVER=database
SESSION_COOKIE=scopetwin_rehearsal_session
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=array
PASSWORD_RECOVERY_ENABLED=false
DEMO_ENABLED=true
DEMO_ALLOW_SETUP=true
BACKBOARD_ENABLED=false
"@ | Set-Content -LiteralPath $releaseFile -Encoding utf8NoBOM
}
$releaseValues = @{}
Get-Content -LiteralPath $releaseFile | ForEach-Object { $pair = $_ -split '=', 2; if ($pair.Count -eq 2) { $releaseValues[$pair[0]] = $pair[1] } }
if ($releaseValues.DB_DATABASE -ne $releaseDatabase -or $releaseValues.RELEASE_PORT -ne $releasePort) { throw 'Unexpected rehearsal target; refusing to continue.' }
$composeArgs = @('compose', '--env-file', $releaseFile, '-p', "scopetwin-$releaseTag", '-f', 'compose.production.yaml')
function Invoke-ReleaseCompose {
    & docker @composeArgs @args
    if ($LASTEXITCODE -ne 0) { throw "Rehearsal Docker operation failed ($LASTEXITCODE)." }
}
Invoke-ReleaseCompose config --quiet
Invoke-ReleaseCompose build app database
Invoke-ReleaseCompose up -d --wait database
$priorPassword = $env:DB_PASSWORD
try {
    $env:DB_PASSWORD = $releaseValues.RELEASE_DB_OWNER_PASSWORD
    Invoke-ReleaseCompose run --rm --no-deps --entrypoint php -e DB_USERNAME=scopetwin_owner -e DB_PASSWORD app artisan migrate --force --no-interaction
    $runtimePassword = $releaseValues.DB_PASSWORD
    $sql = @"
SELECT 'CREATE ROLE scopetwin_runtime LOGIN PASSWORD ''$runtimePassword'''
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'scopetwin_runtime')\gexec
GRANT CONNECT ON DATABASE $releaseDatabase TO scopetwin_runtime;
GRANT USAGE ON SCHEMA public TO scopetwin_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO scopetwin_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO scopetwin_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE scopetwin_owner IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO scopetwin_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE scopetwin_owner IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO scopetwin_runtime;
"@
    $sql | & docker @composeArgs exec -T database psql -v ON_ERROR_STOP=1 -U scopetwin_owner -d $releaseDatabase -q
    if ($LASTEXITCODE -ne 0) { throw 'Runtime role provisioning failed.' }
} finally { $env:DB_PASSWORD = $priorPassword }
Invoke-ReleaseCompose run --rm --no-deps --entrypoint php app artisan scope:judge-demo --confirm-disposable
Invoke-ReleaseCompose up -d --wait app
$ready = Invoke-RestMethod "http://127.0.0.1:$releasePort/ready"
if ($ready.status -ne 'ready') { throw 'Readiness failed.' }
Write-Output "Isolated production-mode rehearsal ready: http://127.0.0.1:$releasePort/demo (loopback HTTP only). No remote deployment."
