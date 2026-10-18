param([switch]$Build035)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
# Hardcoded disposable project; never accepts an arbitrary user database target.
$tag = if ($Build035) { 'build035' } else { 'build03' }
$privateFile = if ($Build035) { 'storage/.env.build035-rehearsal' } else { 'storage/.env.rehearsal' }
$sourceDatabase = "scopetwin_${tag}_rehearsal"
$composeArgs = @('compose','--env-file',$privateFile,'-p',"scopetwin-$tag",'-f','compose.production.yaml')
$restoreDatabase = 'scopetwin_restore_rehearsal_' + [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
$dumpPath = '/tmp/' + $restoreDatabase + '.dump'
function Invoke-RestoreCompose {
    $result = & docker @composeArgs @args
    if ($LASTEXITCODE -ne 0) { throw 'Isolated backup/restore command failed.' }
    $result
}
$tables = @('users','organizations','organization_memberships','agents','agent_api_keys','policies','policy_versions','action_requests','policy_decisions','audit_events','approval_requests','replay_runs','replay_results')
if ($Build035) { $tables += @('advisor_explanations', 'advisor_quotas') }
function Get-DomainFingerprint([string] $databaseName) {
    if ($databaseName -ne $sourceDatabase -and $databaseName -notmatch '^scopetwin_restore_rehearsal_[0-9]+$') { throw 'Unsafe restore target.' }
    $parts = foreach ($table in $tables) { $order = if ($table -eq 'advisor_quotas') { 'key' } else { 'id' }; "SELECT '$table' AS name, count(*) AS rows, md5(coalesce(string_agg(row_to_json(t)::text, '' ORDER BY $order), '')) AS fingerprint FROM $table t" }
    Invoke-RestoreCompose exec -T database psql -U scopetwin_owner -d $databaseName -At -c ($parts -join ' UNION ALL ')
}
$before = Get-DomainFingerprint $sourceDatabase
Invoke-RestoreCompose exec -T database sh -c "umask 077; pg_dump -U scopetwin_owner -d $sourceDatabase -Fc -f $dumpPath"
Invoke-RestoreCompose exec -T database createdb -U scopetwin_owner -T template0 $restoreDatabase
Invoke-RestoreCompose exec -T database pg_restore --exit-on-error -U scopetwin_owner -d $restoreDatabase $dumpPath
$restored = Get-DomainFingerprint $restoreDatabase
$after = Get-DomainFingerprint $sourceDatabase
if (Compare-Object $before $after) { throw 'Source changed during backup; repeat the drill while synthetic writers are idle.' }
if (Compare-Object $before $restored) { throw 'Restored domain hashes/counts differ.' }
$result = @{ database=$restoreDatabase; tables=$tables.Count; identical=$true; timestamp=[DateTimeOffset]::UtcNow.ToString('o'); scope='disposable rehearsal only; original user database untouched' }
$result | ConvertTo-Json | Set-Content "storage/$tag-restore.json"
Write-Output "PASS: PostgreSQL custom backup restored to new isolated database; all $($tables.Count) domain table counts/hashes match."
