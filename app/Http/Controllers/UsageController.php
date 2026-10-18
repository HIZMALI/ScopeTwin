<?php

namespace App\Http\Controllers;

use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\PolicyDecision;
use App\Models\ReplayRun;
use App\Services\OrganizationContext as Context;
use Inertia\Inertia;

class UsageController extends Controller
{
    public function __invoke()
    {
        $org = Context::id();
        $since = now()->subDays(29)->startOfDay();
        $query = PolicyDecision::forOrganization($org)->where('evaluated_at', '>=', $since);
        $daily = (clone $query)->selectRaw('DATE(evaluated_at) as day, decision, count(*) as total')->groupByRaw('DATE(evaluated_at), decision')->get();
        $counts = (clone $query)->selectRaw('decision, count(*) as total')->groupBy('decision')->pluck('total', 'decision');
        $agents = Agent::forOrganization($org)->select(['id', 'name'])->withCount(['decisions' => fn ($q) => $q->where('evaluated_at', '>=', $since)])->orderByDesc('decisions_count')->limit(50)->get();
        $days = collect(range(29, 0))->map(function ($i) use ($daily) {
            $date = now()->subDays($i)->format('Y-m-d');
            $rows = $daily->where('day', $date)->pluck('total', 'decision');

            return ['date' => $date, 'ALLOW' => (int) ($rows['ALLOW'] ?? 0), 'REVIEW' => (int) ($rows['REVIEW'] ?? 0), 'DENY' => (int) ($rows['DENY'] ?? 0)];
        });

        return Inertia::render('scope/usage', ['days' => $days, 'effects' => $counts, 'agents' => $agents,
            'counts' => ['requests' => ActionRequest::forOrganization($org)->where('created_at', '>=', $since)->count(), 'approvals' => ApprovalRequest::forOrganization($org)->where('created_at', '>=', $since)->count(), 'replays' => ReplayRun::forOrganization($org)->where('created_at', '>=', $since)->count()]]);
    }
}
