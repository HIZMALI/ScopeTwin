<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('agent-ingress', fn ($request) => Limit::perMinute(180)->by($request->ip()));
        RateLimiter::for('agent-actions', fn ($request) => Limit::perMinute(60)->by($request->attributes->get('agent_key')->agent_id));
    }
}
