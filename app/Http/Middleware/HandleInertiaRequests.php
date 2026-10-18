<?php

namespace App\Http\Middleware;

use App\Domain\SnapshotManifest;
use App\Models\Organization;
use App\Services\ApprovalService;
use App\Services\OrganizationContext;
use App\Services\PolicySnapshots;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $membership = $request->user() ? OrganizationContext::membership($request->user()) : null;

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'workspace' => $membership ? Organization::findOrFail($membership->organization_id)->only(['id', 'name']) : null,
            'canManage' => $membership && in_array($membership->role, ['OWNER', 'ADMIN'], true),
            'pendingApprovals' => fn () => $membership ? ApprovalService::pendingQuery($membership->organization_id,
                SnapshotManifest::fingerprint(PolicySnapshots::frozen($membership->organization_id)))->count() : 0,
            'auth' => [
                'user' => $request->user(),
            ],
        ]);
    }
}
