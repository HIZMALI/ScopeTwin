<?php

return [
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    'allow_insecure_http' => env('APP_ALLOW_INSECURE_HTTP', false),
    'hsts' => env('HSTS_ENABLED', false),
    'password_recovery' => env('PASSWORD_RECOVERY_ENABLED', false),
    'observe_proxy' => env('RELEASE_OBSERVE_PROXY', false),
    'client_ip_header' => env('TRUSTED_CLIENT_IP_HEADER'),
];
