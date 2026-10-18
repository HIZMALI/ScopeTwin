<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends ScopedModel
{
    protected function casts(): array
    {
        return ['action_summary' => 'array', 'policy_manifest' => 'array', 'expires_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agentKey(): BelongsTo
    {
        return $this->belongsTo(AgentApiKey::class, 'agent_api_key_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(PolicyDecision::class, 'policy_decision_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
