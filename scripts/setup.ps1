$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (!(Test-Path .env)) { Copy-Item .env.example .env }
$contents = Get-Content .env -Raw
if ($contents -notmatch '(?m)^DB_PASSWORD=.+$') {
    $password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $contents = $contents -replace '(?m)^DB_PASSWORD=.*$', "DB_PASSWORD=$password"
    Set-Content .env $contents -NoNewline
}
composer install --no-interaction
if ($LASTEXITCODE) { throw 'Composer failed' }
if ($contents -match '(?m)^APP_KEY=\s*$') {
    php artisan key:generate --no-interaction
    if ($LASTEXITCODE) { throw 'Application key generation failed' }
}
docker compose up -d --wait
if ($LASTEXITCODE) { throw 'PostgreSQL failed to start' }
php artisan migrate --no-interaction
if ($LASTEXITCODE) { throw 'Migration failed' }
npm ci
if ($LASTEXITCODE) { throw 'npm install failed' }
npm run build
if ($LASTEXITCODE) { throw 'Frontend build failed' }
Write-Host 'Ready. Run: php artisan serve --host=127.0.0.1 --port=18731 --no-reload'
