<?php

namespace Goldnead\IdentityContracts\Contracts;

/**
 * Supplies the pseudonymous visitor id used for pre-identification activity.
 *
 * Kept behind a contract because the correct source is application-specific and
 * consent-sensitive: a session value, a first-party cookie, an id handed over by
 * the frontend, or nothing at all. Returning null is always valid and must never
 * be treated as an error.
 */
interface AnonymousIdResolver
{
    public function resolve(): ?string;
}
