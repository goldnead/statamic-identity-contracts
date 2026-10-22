<?php

namespace Goldnead\IdentityContracts\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody was logged in through a mail link. Carries the user and the path
 * they landed on, never the link itself: the signature is a credential.
 */
class AutoLoginSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $path,
    ) {}
}
