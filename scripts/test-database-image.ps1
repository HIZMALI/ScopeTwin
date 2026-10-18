param([ValidateSet('build03', 'build035')][string]$Build = 'build03')
$ErrorActionPreference = 'Stop'
$containerName = "scopetwin-$Build-initcheck"
if (& docker ps -aq --filter "name=^/$containerName`$") { throw 'Init-check container name already exists; refusing to replace it.' }
$priorSecret = $env:POSTGRES_PASSWORD
$created = $false
try {
    $env:POSTGRES_PASSWORD = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    & docker run --rm -d --name $containerName -e POSTGRES_PASSWORD -e POSTGRES_USER=scopetwin_owner -e POSTGRES_DB=scopetwin_init_rehearsal "scopetwin-postgres:$Build" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Fresh isolated database startup failed.' }
    $created = $true
    $ready = $false
    for ($i=0; $i -lt 30; $i++) {
        & docker exec $containerName pg_isready -U scopetwin_owner -d scopetwin_init_rehearsal 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready=$true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'Fresh database did not become ready.' }
    $value = & docker exec $containerName psql -U scopetwin_owner -d scopetwin_init_rehearsal -At -c 'SELECT 1'
    if ($LASTEXITCODE -ne 0 -or $value -ne '1') { throw 'Fresh database query failed.' }
    Write-Output 'PASS: hardened PostgreSQL image initialized a fresh disposable database and answered SELECT 1; no ports published.'
} finally {
    $env:POSTGRES_PASSWORD = $priorSecret
    # Only the explicitly created --rm init-check container; no existing project/volume.
    if ($created) { & docker stop $containerName | Out-Null }
}
