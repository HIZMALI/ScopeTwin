<?php

return [
    'enabled' => env('DEMO_ENABLED', false),
    'allow_setup' => env('DEMO_ALLOW_SETUP', false),
    // A fixed designated ID; public input can never select a workspace.
    'organization_id' => '018f0000-0000-7000-8000-000000000003',
    'slug' => 'scopetwin-synthetic-judge-v1',
];
