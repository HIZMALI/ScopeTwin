<?php

namespace App\Http\Controllers;

use App\Models\ActionRequest;
use App\Models\Agent;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Models\ReplayResult;
use App\Models\ReplayRun;
use App\Services\OrganizationContext as Context;
use App\Services\ReplayAdvisor;
use App\Services\ReplayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReplayController extends Controller
{
    public function index()
    {
        $org = Context::id();

        return Inertia::render('scope/replays', [
            'runs' => ReplayRun::forOrganization($org)->with('actor:id,name')->latest()->orderByDesc('id')->paginate(15),
            'drafts' => PolicyVersion::forOrganization($org)->where('state', 'DRAFT')->whereIn('policy_id', Policy::forOrganization($org)->where('status', 'ACTIVE')->select('id'))->with('policy:id,name')->orderBy('policy_id')->get(),
            'agents' => Agent::forOrganization($org)->orderBy('name')->get(['id', 'name']),
            'historyCount' => ActionRequest::forOrganization($org)->count(),
        ]);
    }

    public function store(Request $request, ReplayService $service)
    {
        Context::authorizeWrite();
        $data = $request->validate(['draft_id' => 'required|uuid', 'limit' => 'sometimes|integer|min:1|max:500',
            'agent_id' => 'nullable|uuid', 'action_name' => ['nullable', 'string', 'max:128', 'regex:/^[a-z][a-z0-9_.:-]*$/D'],
            'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')], 'organization_id' => 'prohibited']);
        if (! empty($data['agent_id'])) {
            Agent::forOrganization(Context::id())->findOrFail($data['agent_id']);
        }
        $draft = $data['draft_id'];
        unset($data['draft_id']);
        $run = $service->create($request->user(), $draft, array_filter($data, fn ($v) => $v !== null && $v !== ''));

        return to_route('replays.show', $run->id);
    }

    public function show(Request $request, string $id, ReplayAdvisor $advisor)
    {
        $run = ReplayRun::forOrganization(Context::id())->with('actor:id,name')->findOrFail($id);
        $filters = $request->validate(['changed' => 'nullable|in:1,0', 'action' => 'nullable|string|max:128', 'effect' => 'nullable|in:ALLOW,REVIEW,DENY']);
        $query = ReplayResult::forOrganization(Context::id())->where('replay_run_id', $id);
        if (isset($filters['changed'])) {
            $query->where('changed', $filters['changed'] === '1');
        }
        if (! empty($filters['action'])) {
            $query->where('input_snapshot->action_name', $filters['action']);
        }
        if (! empty($filters['effect'])) {
            $query->where('candidate_effect', $filters['effect']);
        }

        return Inertia::render('scope/replay', ['run' => $run, 'results' => $query->orderByDesc('changed')->orderBy('id')->paginate(20)->withQueryString(), 'filters' => $filters, 'advisor' => $advisor->view($run)]);
    }

    public function result(string $id, string $resultId)
    {
        $run = ReplayRun::forOrganization(Context::id())->findOrFail($id);
        $result = ReplayResult::forOrganization(Context::id())->where('replay_run_id', $id)->findOrFail($resultId);

        return Inertia::render('scope/replay-result', ['run' => $run, 'result' => $result]);
    }
}
