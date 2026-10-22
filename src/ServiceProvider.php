<?php

namespace Goldnead\IdentityContracts;

use Goldnead\IdentityContracts\AutoLogin\AutoLogin;
use Goldnead\IdentityContracts\Contracts\AnonymousIdResolver;
use Goldnead\IdentityContracts\Contracts\ContactLocator;
use Goldnead\IdentityContracts\Http\Controllers\AutoLoginController;
use Goldnead\IdentityContracts\Resolvers\NullContactLocator;
use Goldnead\IdentityContracts\Resolvers\SessionAnonymousIdResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Foundation package: the identity contracts, and the auto login link that
 * mail-rendering addons share. It owns no data (no migrations, no models) and
 * no control panel surface, so there is nothing to permission. The one route it
 * adds answers 404 until the host switches auto login on.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/identity-contracts.php', 'identity-contracts');

        $this->app->singleton('identity-context', fn () => new IdentityManager);
        $this->app->alias('identity-context', IdentityManager::class);

        // Both defaults are deliberately inert: an application without a CRM or
        // without anonymous tracking must still be able to ask for either.
        $this->app->bind(ContactLocator::class, NullContactLocator::class);
        $this->app->bind(AnonymousIdResolver::class, SessionAnonymousIdResolver::class);

        $this->app->singleton(AutoLogin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/identity-contracts.php' => config_path('identity-contracts.php'),
        ], 'identity-contracts-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'identity-contracts');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'identity-contracts');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/identity-contracts'),
        ], 'identity-contracts-views');

        RateLimiter::for('identity-autologin', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('identity-contracts.autologin.rate_limit', 30)),
        )->by((string) $request->ip()));

        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', 'throttle:identity-autologin'])->group(function () {
                Route::match(['get', 'post'], (string) config('identity-contracts.autologin.route', '!/autologin'), AutoLoginController::class)
                    ->name(AutoLogin::ROUTE);
            });
        }
    }
}
