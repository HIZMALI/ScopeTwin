<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Hash-only local regression guard. Never writes database contents or secrets.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$build = in_array($argv[2] ?? '', ['build03', 'build035', 'build035-pg'], true) ? $argv[2] : 'build02';
if (! str_starts_with($build, 'build035') && (! app()->environment('local') || config('database.connections.'.config('database.default').'.database') !== 'scopetwin')) {
    throw new RuntimeException('This guard is only for the local scopetwin database.');
}
$path = storage_path($build.'-data-guard.json');
$tables = ['users', 'organizations', 'organization_memberships', 'agents', 'agent_api_keys', 'policies', 'policy_versions', 'action_requests', 'policy_decisions', 'audit_events'];
if (in_array($build, ['build03', 'build035', 'build035-pg'], true)) {
    $tables = [...$tables, 'approval_requests', 'replay_runs', 'replay_results'];
}
if (($argv[1] ?? '') === 'capture') {
    if (file_exists($path)) {
        throw new RuntimeException('Guard already exists; refusing to replace baseline.');
    }
    $guard = ['env' => hash_file('sha256', base_path('.env')), 'tables' => []];
    foreach ($tables as $table) {
        if (! Schema::hasTable($table)) {
            continue;
        }
        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $guard['tables'][$table][$row->id] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        }
    }
    file_put_contents($path, json_encode($guard, JSON_THROW_ON_ERROR));
    echo "Local preservation baseline captured (hashes only).\n";
} else {
    $guard = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if ($guard['env'] !== hash_file('sha256', base_path('.env'))) {
        throw new RuntimeException('Environment file changed.');
    }
    $count = 0;
    foreach ($guard['tables'] as $table => $records) {
        foreach ($records as $id => $hash) {
            $row = DB::table($table)->where('id', $id)->first();
            if (! $row || ! hash_equals($hash, hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)))) {
                throw new RuntimeException('An original row changed in '.$table);
            }
            $count++;
        }
    }
    echo "PASS: .env and all $count original domain rows preserved byte-for-byte in canonical query output.\n";
}
