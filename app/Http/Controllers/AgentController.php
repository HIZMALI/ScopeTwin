<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Services\AgentKeys;
use App\Services\Audit;
use App\Services\OrganizationContext as Context;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index()
    {
        return Inertia::render('scope/agents', ['agents' => Agent::forOrganization(Context::id())->withCount(['keys as active_keys_count' => fn ($q) => $q->whereNull('revoked_at')])->latest()->get()]);
    }

    public function store(Request $request)
    {
        Context::authorizeWrite();
        $data = $request->validate(['name' => 'required|string|max:100', 'organization_id' => 'prohibited']);
        $result = DB::transaction(function () use ($data, $request) {
            $agent = Agent::create(['organization_id' => Context::id(), 'name' => $data['name'], 'status' => 'ACTIVE']);
            $token = AgentKeys::issue($agent);
            Audit::record($agent->organization_id, 'agent.created', $agent->id, 'USER', (string) $request->user()->id);

            return ['agent_id' => $agent->id, 'token' => $token];
        });

        return response()->json($result, 201)->header('Cache-Control', 'no-store, private');
    }

    public function show(string $id)
    {
        return Inertia::render('scope/agent', ['agent' => Agent::forOrganization(Context::id())->with('keys')->findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        Context::authorizeWrite();
        $data = $request->validate(['name' => 'required|string|max:100', 'status' => 'required|in:ACTIVE,DISABLED', 'organization_id' => 'prohibited']);
        DB::transaction(function () use ($data, $id, $request) {
            $agent = Agent::forOrganization(Context::id())->lockForUpdate()->findOrFail($id);
            $agent->update($data);
            if ($data['status'] === 'DISABLED') {
                $agent->keys()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            Audit::record($agent->organization_id, 'agent.updated', $agent->id, 'USER', (string) $request->user()->id, ['status' => $data['status']]);
        });

        return back();
    }

    public function key(Request $request, string $id)
    {
        Context::authorizeWrite();
        $token = DB::transaction(function () use ($id, $request) {
            $agent = Agent::forOrganization(Context::id())->lockForUpdate()->findOrFail($id);
            abort_unless($agent->status === 'ACTIVE', 409, 'Enable the agent before issuing a key.');
            $token = AgentKeys::issue($agent);
            Audit::record($agent->organization_id, 'agent.key_issued', $agent->id, 'USER', (string) $request->user()->id);

            return $token;
        });

        return response()->json(['token' => $token], 201)->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request, string $id, string $keyId)
    {
        Context::authorizeWrite();
        DB::transaction(function () use ($id, $keyId, $request) {
            $agent = Agent::forOrganization(Context::id())->lockForUpdate()->findOrFail($id);
            $key = AgentApiKey::forOrganization(Context::id())->where('agent_id', $agent->id)->findOrFail($keyId);
            $key->update(['revoked_at' => now()]);
            Audit::record($agent->organization_id, 'agent.key_revoked', $key->id, 'USER', (string) $request->user()->id);
        });

        return back();
    }
}
