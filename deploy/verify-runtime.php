<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$role = DB::selectOne("SELECT rolsuper, rolcreatedb, rolcreaterole, has_schema_privilege(current_user, 'public', 'CREATE') AS schema_create FROM pg_roles WHERE rolname = current_user");
if (! app()->isProduction() || config('app.debug') || ! config('app.key') || file_exists(base_path('.env')) ||
    $role->rolsuper || $role->rolcreatedb || $role->rolcreaterole || $role->schema_create ||
    ! file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')) || getenv('RELEASE_DB_OWNER_PASSWORD') ||
    (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
    throw new RuntimeException('Unsafe production runtime or database privilege detected.');
}
echo "PASS: production/debug-off, non-root, built assets, no .env/hot file; DB role has no superuser, role/database/schema creation privileges.\n";
