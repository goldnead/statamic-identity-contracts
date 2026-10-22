<?php

namespace Goldnead\IdentityContracts\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A users-table backed model, for the auto login tests only. */
class AutoLoginUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['email_verified_at' => 'datetime', 'super' => 'boolean'];
}
