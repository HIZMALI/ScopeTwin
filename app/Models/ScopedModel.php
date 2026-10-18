<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class ScopedModel extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where($this->qualifyColumn('organization_id'), $organizationId);
    }
}
