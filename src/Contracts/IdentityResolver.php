<?php

namespace Goldnead\IdentityContracts\Contracts;

use Goldnead\IdentityContracts\Identity;

/**
 * Turns an arbitrary subject (a user model, an email, an id, null) into an
 * Identity. Implementations return null when they do not recognise the subject
 * so the manager can fall through to the next resolver.
 */
interface IdentityResolver
{
    public function resolve(mixed $subject): ?Identity;
}
