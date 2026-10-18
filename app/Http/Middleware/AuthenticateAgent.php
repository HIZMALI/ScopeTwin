<?php

namespace App\Http\Middleware;

use App\Models\AgentApiKey;
use App\Services\AgentKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

final class AuthenticateAgent
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? '';
        if (! preg_match('/^st_([a-f0-9]{16})_[a-f0-9]{64}$/D', $token, $m)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $key = AgentApiKey::where('prefix', $m[1])->whereNull('revoked_at')->with('agent')->first();
        if (! $key || ! hash_equals($key->key_hash, AgentKeys::hash($token)) || $key->agent->status !== 'ACTIVE'
            || $key->organization_id !== $key->agent->organization_id) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $request->attributes->set('agent_key', $key);

        // Apply the identity limiter here: Laravel prioritizes route throttle middleware
        // before custom authentication middleware, when the agent is not resolved yet.
        return app(ThrottleRequests::class)->handle($request, $next, 'agent-actions');
    }
}
