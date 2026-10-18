<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentApiKey extends ScopedModel
{
    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
