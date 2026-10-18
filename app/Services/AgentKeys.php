<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentApiKey;

final class AgentKeys
{
    public static function hash(string $token): string
    {
        return hash_hmac('sha256', $token, config('app.key'));
    }

    public static function issue(Agent $agent): string
    {
        $prefix = bin2hex(random_bytes(8));
        $token = 'st_'.$prefix.'_'.bin2hex(random_bytes(32));
        AgentApiKey::create([
            'organization_id' => $agent->organization_id, 'agent_id' => $agent->id,
            'prefix' => $prefix, 'key_hash' => self::hash($token),
        ]);

        return $token;
    }
}
