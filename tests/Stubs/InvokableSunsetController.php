<?php

namespace Jarryd\Sunset\Tests\Stubs;

use Jarryd\Sunset\Sunset;

class InvokableSunsetController
{
    #[Sunset('2027-09-01', since: '2026-11-01')]
    public function __invoke(): string
    {
        return 'invoked';
    }
}
