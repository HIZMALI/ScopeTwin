<?php

namespace App\Http\Controllers;

use App\Services\ReplayAdvisor;
use Illuminate\Http\Request;

final class ReplayAdviceController extends Controller
{
    public function __invoke(Request $request, string $id, ReplayAdvisor $advisor)
    {
        $result = $advisor->advise($request->user(), $id, $request->except('_token'));
        $status = match ($result['status']) {
            'completed' => 200, 'in_progress' => 202, 'quota_exhausted' => 429,
            'disabled', 'not_configured' => 503, default => 502,
        };

        unset($result['diagnostics']);

        return response()->json($result, $status)->header('Cache-Control', 'no-store');
    }
}
