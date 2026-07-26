<?php

namespace Goldnead\IdentityContracts\Tests\Fixtures;

use Goldnead\IdentityContracts\Contracts\ProvidesIdentity;
use Goldnead\IdentityContracts\Identity;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A host model that opts into describing itself — the seam that lets an
 * application keep its own identity semantics without the addon knowing them.
 */
class SelfDescribingUser extends Authenticatable implements ProvidesIdentity
{
    protected $guarded = [];

    public $timestamps = false;

    public function toIdentity(): Identity
    {
        return Identity::user(
            id: $this->getAttribute('id'),
            email: $this->getAttribute('email'),
            name: $this->getAttribute('name'),
            contactUuid: $this->getAttribute('contact_uuid'),
            meta: ['role' => $this->getAttribute('role')],
        );
    }
}
