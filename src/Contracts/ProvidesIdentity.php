<?php

namespace Goldnead\IdentityContracts\Contracts;

use Goldnead\IdentityContracts\Identity;

/**
 * Implemented by host-application models (typically the User model) that want
 * full control over how they are represented to the addon family. This is the
 * opt-in seam that keeps addons free of `App\Models\…` dependencies: the addon
 * asks for an Identity, the application decides what that means.
 */
interface ProvidesIdentity
{
    public function toIdentity(): Identity;
}
