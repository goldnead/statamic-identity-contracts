<?php

namespace Goldnead\IdentityContracts\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Stands in for a host application's User model — no table, only attributes, so
 * the contracts package never needs a schema of its own.
 */
class FixtureUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;
}
