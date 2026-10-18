<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ephemeral test-only encryption key; never generate or replace the user's .env key.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'http://127.0.0.1']);
        $this->app['url']->forceRootUrl('http://127.0.0.1');
        Http::preventStrayRequests();
    }
}
