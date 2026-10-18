<?php

namespace App\Services;

use App\Models\Policy;
use App\Models\PolicyVersion;

final class PolicySnapshots
{
    public static function published(string $org): array
    {
        return PolicyVersion::forOrganization($org)->where('state', 'PUBLISHED')->whereIn('id',
            Policy::forOrganization($org)->where('status', 'ACTIVE')->whereNotNull('published_version_id')->select('published_version_id')
        )->get()->toArray();
    }
}
