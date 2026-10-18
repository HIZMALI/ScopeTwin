<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyDecision extends ScopedModel
{
    protected function casts(): array
    {
        return ['evaluated_at' => 'datetime'];
    }

    public function actionRequest(): BelongsTo
    {
        return $this->belongsTo(ActionRequest::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class);
    }
}
