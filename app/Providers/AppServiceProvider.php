<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // laravel/telescope is a require-dev only dependency. Registering its
        // service providers unconditionally in bootstrap/providers.php causes a
        // fatal "class not found" error on every request in any environment
        // built with `composer install --no-dev` (e.g. this app's production
        // Docker image), since the package is never installed there. Only
        // register Telescope when the package is actually present.
        if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('webhook-trigger', function (Request $request) {
            return Limit::perMinute(config('webhooks.rate_limit', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('secret-rotation', function (Request $request) {
            return Limit::perMinute(config('webhooks.secret_rotation_rate_limit', 5))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
