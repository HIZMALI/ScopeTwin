$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$previousConnection = $env:DB_CONNECTION
$previousDatabase = $env:DB_DATABASE
try {
    $exists = docker compose exec -T postgres psql -U scopetwin -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = 'scopetwin_test'"
    if ($LASTEXITCODE) { throw 'PostgreSQL unavailable' }
    if ($exists -ne '1') {
        docker compose exec -T postgres psql -U scopetwin -d postgres -c 'CREATE DATABASE scopetwin_test;'
        if ($LASTEXITCODE) { throw 'Test database creation failed' }
    }
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_DATABASE = 'scopetwin_test'
    php artisan test --compact
    if ($LASTEXITCODE) { throw 'PostgreSQL tests failed' }
    php artisan migrate:rollback --step=1 --force
    if ($LASTEXITCODE) { throw 'Domain migration rollback failed' }
    php artisan migrate --force
    if ($LASTEXITCODE) { throw 'Domain migration replay failed' }
    php tests/scripts/concurrency.php
    if ($LASTEXITCODE) { throw 'Concurrency checks failed' }
} finally {
    $env:DB_CONNECTION = $previousConnection
    $env:DB_DATABASE = $previousDatabase
}
