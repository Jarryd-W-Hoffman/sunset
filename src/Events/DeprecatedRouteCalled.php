<?php

namespace Jarryd\Sunset\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jarryd\Sunset\Sunset;

/**
 * Dispatched when a sunset route is called, at most once per caller, route
 * and method each day, so listeners can send alerts without flooding.
 */
class DeprecatedRouteCalled
{
    use Dispatchable;

    public function __construct(
        public readonly string $route,
        public readonly string $method,
        public readonly string $caller,
        public readonly Sunset $sunset,
        public readonly bool $pastSunset,
    ) {}
}
