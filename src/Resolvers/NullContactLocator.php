<?php

namespace Goldnead\IdentityContracts\Resolvers;

use Goldnead\IdentityContracts\Contracts\ContactLocator;
use Goldnead\IdentityContracts\Identity;

/**
 * Default binding for applications without a CRM. Every lookup misses, which is
 * the correct answer rather than an error condition.
 */
class NullContactLocator implements ContactLocator
{
    public function locateByEmail(string $email): ?Identity
    {
        return null;
    }

    public function locateByUuid(string $uuid): ?Identity
    {
        return null;
    }
}
