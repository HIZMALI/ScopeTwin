<?php

namespace App\Services;

use App\Domain\SnapshotManifest;
use App\Models\Organization;
use App\Models\Policy;
use App\Models\PolicyVersion;

final class PolicySnapshots
{
    public static function lock(string $org, bool $exclusive = false): void
    {
        $query = Organization::whereKey($org);
        ($exclusive ? $query->lockForUpdate() : $query->sharedLock())->firstOrFail();
    }

    public static function frozen(string $org): array
    {
        $rules = array_map(SnapshotManifest::rule(...), self::published($org));
        usort($rules, fn ($a, $b) => [$a['policy_id'], $a['version']] <=> [$b['policy_id'], $b['version']]);

        return SnapshotManifest::bounded($rules);
    }

    public static function published(string $org): array
    {
        return PolicyVersion::forOrganization($org)->where('state', 'PUBLISHED')->whereIn('id',
            Policy::forOrganization($org)->where('status', 'ACTIVE')->whereNotNull('published_version_id')->select('published_version_id')
        )->get()->toArray();
    }
}
