<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AuditEvent;
use App\Models\Policy;
use App\Models\PolicyDecision;
use App\Services\OrganizationContext as Context;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function dashboard()
    {
        $org = Context::id();
        $query = PolicyDecision::forOrganization($org);
        $counts = (clone $query)->selectRaw('decision, count(*) as total')->groupBy('decision')->pluck('total', 'decision');
        $daily = (clone $query)->where('evaluated_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(evaluated_at) as day, count(*) as total')->groupByRaw('DATE(evaluated_at)')->pluck('total', 'day');

        return Inertia::render('scope/overview', [
            'counts' => ['total' => $counts->sum(), 'allow' => (int) ($counts['ALLOW'] ?? 0), 'review' => (int) ($counts['REVIEW'] ?? 0),
                'deny' => (int) ($counts['DENY'] ?? 0), 'agents' => Agent::forOrganization($org)->where('status', 'ACTIVE')->count(),
                'policies' => Policy::forOrganization($org)->where('status', 'ACTIVE')->whereNotNull('published_version_id')->count()],
            'traffic' => collect(range(6, 0))->map(fn ($i) => ['date' => now()->subDays($i)->format('D'), 'count' => (int) $daily->get(now()->subDays($i)->format('Y-m-d'), 0)]),
            'recent' => (clone $query)->with(['agent:id,name', 'actionRequest:id,action_name,amount_minor,currency'])->orderByDesc('evaluated_at')->orderByDesc('id')->limit(6)->get(),
        ]);
    }

    public function decisions(Request $request)
    {
        $filters = $request->validate(['decision' => 'nullable|in:ALLOW,REVIEW,DENY', 'action' => 'nullable|string|max:128']);
        $query = PolicyDecision::forOrganization(Context::id())->with(['agent:id,name', 'actionRequest:id,action_name,amount_minor,currency']);
        if (! empty($filters['decision'])) {
            $query->where('decision', $filters['decision']);
        }
        if (! empty($filters['action'])) {
            $query->whereHas('actionRequest', fn ($q) => $q->where('action_name', $filters['action']));
        }

        return Inertia::render('scope/decisions', ['decisions' => $query->orderByDesc('evaluated_at')->orderByDesc('id')->paginate(15)->withQueryString(), 'filters' => $filters]);
    }

    public function decision(string $id)
    {
        return Inertia::render('scope/decision', ['decision' => PolicyDecision::forOrganization(Context::id())->with(['agent:id,name', 'actionRequest', 'policyVersion', 'approval:id,policy_decision_id,status'])->findOrFail($id)]);
    }

    public function audit(string $id)
    {
        return response()->json(AuditEvent::forOrganization(Context::id())->findOrFail($id));
    }
}
