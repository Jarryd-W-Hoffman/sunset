<?php

namespace Jarryd\Sunset\Tests\Stubs;

use Illuminate\Foundation\Auth\User;

/**
 * Stands in for a Sanctum user, without requiring Sanctum.
 */
class TokenUser extends User
{
    public ?object $token = null;

    public function currentAccessToken(): ?object
    {
        return $this->token;
    }
}
