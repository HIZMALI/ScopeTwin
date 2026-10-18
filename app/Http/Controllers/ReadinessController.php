<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController
{
    public function __invoke()
    {
        try {
            if (app()->isProduction() && (config('app.debug') || ! config('app.key') ||
                (! str_starts_with(config('app.url'), 'https://') && ! config('security.allow_insecure_http')))) {
                return response()->json(['status' => 'unavailable'], 503);
            }
            DB::select('SELECT 1');
            // Verify migrated domain and DB-backed session/cache infrastructure without writes.
            foreach (['policy_versions', 'approval_requests', 'replay_runs', 'sessions', 'cache'] as $table) {
                DB::table($table)->limit(1)->exists();
            }

            return response()->json(['status' => 'ready']);
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
