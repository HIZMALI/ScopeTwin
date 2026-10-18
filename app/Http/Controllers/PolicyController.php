<?php

namespace App\Http\Controllers;

use App\Domain\ActionSchema;
use App\Domain\ConditionSchema;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Services\Audit;
use App\Services\OrganizationContext as Context;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PolicyController extends Controller
{
    public function index()
    {
        return Inertia::render('scope/policies', ['policies' => Policy::forOrganization(Context::id())->with('versions')->latest()->get()]);
    }

    public function create()
    {
        Context::authorizeWrite();

        return Inertia::render('scope/policy-form', ['policy' => null, 'currencies' => ActionSchema::CURRENCIES]);
    }

    public function show(string $id)
    {
        return Inertia::render('scope/policy-form', ['policy' => Policy::forOrganization(Context::id())->with('versions')->findOrFail($id), 'currencies' => ActionSchema::CURRENCIES]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'effect' => 'required|in:ALLOW,REVIEW,DENY',
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/D'],
            'conditions' => 'required|array', 'status' => 'sometimes|in:ACTIVE,DISABLED', 'organization_id' => 'prohibited',
        ]);
        $data['conditions'] = ConditionSchema::validate($data['conditions']);

        return $data;
    }

    public function store(Request $request)
    {
        Context::authorizeWrite();
        $data = $this->validated($request);
        $policy = DB::transaction(function () use ($data, $request) {
            $policy = Policy::create(['organization_id' => Context::id(), 'name' => $data['name'], 'status' => 'ACTIVE']);
            $this->saveDraft($policy, $data);
            Audit::record($policy->organization_id, 'policy.created', $policy->id, 'USER', (string) $request->user()->id);

            return $policy;
        });

        return to_route('policies.show', $policy->id);
    }

    public function update(Request $request, string $id)
    {
        Context::authorizeWrite();
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $id, $request) {
            $policy = Policy::forOrganization(Context::id())->lockForUpdate()->findOrFail($id);
            $policy->update(['name' => $data['name'], 'status' => $data['status'] ?? $policy->status]);
            $this->saveDraft($policy, $data);
            Audit::record($policy->organization_id, 'policy.draft_saved', $policy->id, 'USER', (string) $request->user()->id);
        });

        return back();
    }

    private function saveDraft(Policy $policy, array $data): void
    {
        $version = $policy->versions()->where('state', 'DRAFT')->first();
        $values = ['conditions' => $data['conditions'], 'effect' => $data['effect'], 'reason_code' => $data['reason_code']];
        if ($version) {
            $version->update($values);
        } else {
            $policy->versions()->create([...$values, 'organization_id' => $policy->organization_id, 'version' => ($policy->versions()->max('version') ?? 0) + 1, 'state' => 'DRAFT']);
        }
    }

    public function publish(Request $request, string $id)
    {
        Context::authorizeWrite();
        $data = $request->validate(['draft_id' => 'required|uuid']);
        DB::transaction(function () use ($id, $data, $request) {
            $policy = Policy::forOrganization(Context::id())->lockForUpdate()->findOrFail($id);
            $draft = PolicyVersion::forOrganization(Context::id())->where('policy_id', $id)->where('state', 'DRAFT')->whereKey($data['draft_id'])->first();
            abort_unless($draft && $policy->status === 'ACTIVE', 409, 'Save an active draft before publishing.');
            ConditionSchema::validate($draft->conditions);
            $draft->update(['state' => 'PUBLISHED', 'published_at' => now()]);
            $policy->update(['published_version_id' => $draft->id]);
            Audit::record($policy->organization_id, 'policy.published', $draft->id, 'USER', (string) $request->user()->id, ['version' => $draft->version]);
        });

        return back();
    }

    public function disable(Request $request, string $id)
    {
        Context::authorizeWrite();
        DB::transaction(function () use ($id, $request) {
            $policy = Policy::forOrganization(Context::id())->lockForUpdate()->findOrFail($id);
            $policy->update(['status' => 'DISABLED']);
            Audit::record($policy->organization_id, 'policy.disabled', $policy->id, 'USER', (string) $request->user()->id);
        });

        return back();
    }
}
