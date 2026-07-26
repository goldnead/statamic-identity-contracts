<?php

namespace Goldnead\IdentityContracts\Facades;

use Closure;
use Goldnead\IdentityContracts\Contracts\IdentityResolver;
use Goldnead\IdentityContracts\Identity;
use Goldnead\IdentityContracts\IdentityManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Identity current()
 * @method static Identity resolve(mixed $subject)
 * @method static mixed actingAs(mixed $subject, Closure $callback)
 * @method static IdentityManager setCurrent(mixed $subject)
 * @method static IdentityManager forget()
 * @method static IdentityManager resolveUsing(IdentityResolver|Closure $resolver)
 * @method static Identity system(?string $id = null)
 * @method static Identity|null locateContact(string $email)
 * @method static Identity withContact(Identity $identity)
 * @method static string|null anonymousId()
 *
 * @see IdentityManager
 */
class IdentityContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'identity-context';
    }
}
