<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplayRun extends ScopedModel
{
    protected function casts(): array
    {
        return ['baseline_manifest' => 'array', 'candidate_snapshot' => 'array', 'sample_manifest' => 'array', 'filters' => 'array', 'summary' => 'array', 'completed_at' => 'datetime'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReplayResult::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
