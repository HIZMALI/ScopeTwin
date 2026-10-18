$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$paths = @(& git ls-files --cached --others --exclude-standard) | Sort-Object -Unique
$patterns = @('APP_KEY=base64:[A-Za-z0-9+/=]{40,}', 'st_[a-f0-9]{16}_[a-f0-9]{64}', '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----', 'AKIA[0-9A-Z]{16}')
$hits = @()
foreach ($path in $paths) {
    if ((Test-Path -LiteralPath $path -PathType Leaf) -and $path -notmatch '\.(png|jpg|jpeg|gif|webp|ico|woff2?|mp4)$') {
        $content = Get-Content -LiteralPath $path -Raw
        foreach ($pattern in $patterns) {
            if ($content -cmatch $pattern) { $hits += $path; break }
        }
    }
}
if ($hits.Count) { throw ('Secret-pattern matches (contents withheld): ' + ($hits -join ', ')) }
& git check-ignore -q .env
if ($LASTEXITCODE -ne 0) { throw '.env is not ignored.' }
Write-Output "PASS: $($paths.Count) source paths scanned; no configured secret patterns. Pattern scan is not an independent security audit."
