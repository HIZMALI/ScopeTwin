<?php

namespace App\Http\Controllers;

use App\Domain\SnapshotManifest;
use App\Models\ApprovalRequest;
use App\Models\PolicyDecision;
use App\Services\ApprovalService;
use App\Services\OrganizationContext as Context;
use App\Services\PolicySnapshots;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $org = Context::id();
        $data = $request->validate(['tab' => 'nullable|in:pending,resolved,historical']);
        $tab = $data['tab'] ?? 'pending';
        $fingerprint = SnapshotManifest::fingerprint(PolicySnapshots::frozen($org));
        $pending = ApprovalService::pendingQuery($org, $fingerprint);
        $historical = PolicyDecision::forOrganization($org)->where('decision', 'REVIEW')->whereDoesntHave('approval');
        $counts = ['pending' => (clone $pending)->count(), 'resolved' => ApprovalRequest::forOrganization($org)->whereNotIn('id', (clone $pending)->select('id'))->count(), 'historical' => (clone $historical)->count()];
        $query = ApprovalRequest::forOrganization($org)->with(['agent:id,name,status', 'agentKey', 'decision', 'resolver:id,name']);
        if ($tab === 'pending') {
            $query->whereIn('id', (clone $pending)->select('id'));
        } else {
            $query->whereNotIn('id', (clone $pending)->select('id'));
        }
        $approvals = $query->latest()->orderByDesc('id')->paginate(15)->withQueryString();
        $approvals->through(fn ($approval) => ApprovalService::present($approval, $fingerprint));

        return Inertia::render('scope/approvals', ['approvals' => $approvals, 'counts' => $counts, 'tab' => $tab,
            'historical' => $tab === 'historical' ? $historical->with(['agent:id,name', 'actionRequest'])->latest('evaluated_at')->paginate(15)->withQueryString() : null]);
    }

    public function show(string $id)
    {
        $org = Context::id();
        $approval = ApprovalRequest::forOrganization($org)->with(['agent:id,name,status', 'agentKey', 'decision', 'resolver:id,name'])->findOrFail($id);

        return Inertia::render('scope/approval', ['approval' => ApprovalService::present($approval, SnapshotManifest::fingerprint(PolicySnapshots::frozen($org)))]);
    }

    public function resolve(Request $request, string $id, ApprovalService $service)
    {
        Context::authorizeWrite();
        $data = $request->validate(['status' => 'required|in:APPROVED,REJECTED', 'organization_id' => 'prohibited']);
        $result = $service->resolve($request->user(), $id, $data['status']);

        return response()->json($result, $result['conflict'] ? 409 : 200)->header('Cache-Control', 'no-store, private');
    }

    public function agentStatus(Request $request, string $id)
    {
        $key = $request->attributes->get('agent_key');
        $decision = PolicyDecision::forOrganization($key->organization_id)->where('agent_id', $key->agent_id)->findOrFail($id);
        $approval = ApprovalRequest::forOrganization($key->organization_id)->where('agent_id', $key->agent_id)->where('policy_decision_id', $id)->with(['agent', 'agentKey'])->first();
        $state = $approval ? ApprovalService::present($approval, SnapshotManifest::fingerprint(PolicySnapshots::frozen($key->organization_id))) : null;

        return response()->json(['decision_id' => $id, 'approval_id' => $approval?->id,
            'status' => $state['status'] ?? ($decision->decision === 'REVIEW' ? 'REEVALUATION_REQUIRED' : 'NOT_REQUIRED'),
            'expires_at' => $approval?->expires_at, 'resolved_at' => $approval?->resolved_at, 'execution_performed' => false])->header('Cache-Control', 'no-store, private');
    }
}
