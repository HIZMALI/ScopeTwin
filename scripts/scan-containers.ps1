$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$scanner = 'aquasec/trivy@sha256:2416d1f74045b57274df1a340eee6b510cacd73c66a6ccb8da092b117eb7d222'
foreach ($target in @(@{name='app';image='scopetwin:build03'},@{name='database';image='scopetwin-postgres:build03'})) {
    $archive = Join-Path (Get-Location) ('storage/build03-scan-' + $target.name + '.tar')
    $report = Join-Path (Get-Location) ('storage/build03-scan-' + $target.name + '.json')
    & docker image save -o $archive $target.image
    if ($LASTEXITCODE -ne 0) { throw 'Image export failed.' }
    New-Item -ItemType File -Path $report -Force | Out-Null
    # Scanner receives only this image archive and output file, never application env/data.
    & docker run --rm -v "${archive}:/input/image.tar:ro" -v "${report}:/output/report.json" -v scopetwin-build03-trivy-cache:/root/.cache $scanner image --input /input/image.tar --scanners vuln --severity HIGH,CRITICAL --format json --output /output/report.json --exit-code 1
    if ($LASTEXITCODE -ne 0) { throw "Container security gate failed for $($target.name); inspect the private report." }
    $result = Get-Content -LiteralPath $report -Raw | ConvertFrom-Json
    $findings = @($result.Results | ForEach-Object { $_.Vulnerabilities } | Where-Object { $_ })
    if ($findings.Count -gt 0) { throw 'Unexpected high/critical findings.' }
    Write-Output "PASS: $($target.name) image; 0 HIGH / CRITICAL, feed timestamp $($result.CreatedAt)."
}
