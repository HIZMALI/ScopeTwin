<?php

namespace App\Providers;

use App\Logging\RedactSecrets;
use App\Services\OrganizationContext;
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
        foreach (config('logging.channels') as $name => $channel) {
            config(["logging.channels.$name.tap" => [...($channel['tap'] ?? []), RedactSecrets::class]]);
        }
        RateLimiter::for('registration', fn ($request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-recovery', fn ($request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('replays', function ($request) {
            $org = OrganizationContext::id();

            return [Limit::perMinute(5)->by('replay-user:'.$request->user()->id),
                Limit::perMinute(10)->by('replay-org:'.$org)];
        });
        RateLimiter::for('agent-ingress', fn ($request) => Limit::perMinute(180)->by($request->ip()));
        RateLimiter::for('agent-actions', fn ($request) => Limit::perMinute(60)->by($request->attributes->get('agent_key')->agent_id));
    }
}
