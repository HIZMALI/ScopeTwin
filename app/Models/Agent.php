<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends ScopedModel
{
    public function decisions(): HasMany
    {
        return $this->hasMany(PolicyDecision::class);
    }

    public function keys(): HasMany
    {
        return $this->hasMany(AgentApiKey::class);
    }
}
