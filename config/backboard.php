<?php

return [
    'enabled' => (bool) env('BACKBOARD_ENABLED', false),
    'api_key' => env('BACKBOARD_API_KEY', ''),
    // Dedicated operator-approved assistant. Never fall back to the account default.
    'assistant_id' => env('BACKBOARD_ASSISTANT_ID', ''),
    // No guessed model: operator must verify availability, JSON support and prices privately.
    'llm_provider' => env('BACKBOARD_LLM_PROVIDER', ''),
    'model_name' => env('BACKBOARD_MODEL_NAME', ''),
    'timeout_seconds' => (int) env('BACKBOARD_TIMEOUT_SECONDS', 12),
    'daily_calls' => (int) env('BACKBOARD_DAILY_CALLS', 3),
    'user_calls_per_minute' => (int) env('BACKBOARD_USER_CALLS_PER_MINUTE', 1),
    'cache_seconds' => (int) env('BACKBOARD_CACHE_SECONDS', 900),
];
