<?php

namespace Goldnead\IdentityContracts;

use Goldnead\IdentityContracts\Contracts\AnonymousIdResolver;
use Goldnead\IdentityContracts\Contracts\ContactLocator;
use Goldnead\IdentityContracts\Resolvers\NullContactLocator;
use Goldnead\IdentityContracts\Resolvers\SessionAnonymousIdResolver;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Contracts-only foundation package: no migrations, no models, no CP surface.
 * It owns no data, so there is nothing to permission — consuming addons carry
 * their own CP permissions.
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
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/identity-contracts.php' => config_path('identity-contracts.php'),
        ], 'identity-contracts-config');
    }
}
