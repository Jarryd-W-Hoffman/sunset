<?php

namespace Jarryd\Sunset\Tests\Stubs;

use Jarryd\Sunset\Sunset;

class MethodSunsetController
{
    #[Sunset('2027-03-01', replacement: 'reports.index', reason: 'Use the new reports')]
    public function legacy(): string
    {
        return 'legacy';
    }

    public function current(): string
    {
        return 'current';
    }
}
