<?php

namespace App\Providers;

use App\Services\KeyVault;
use App\Services\VaultService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(KeyVault::class, fn ($app) => new KeyVault($app['request']));
        $this->app->scoped(VaultService::class, fn ($app) => new VaultService($app->make(KeyVault::class)));
    }

    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth-steps', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('secrets', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
