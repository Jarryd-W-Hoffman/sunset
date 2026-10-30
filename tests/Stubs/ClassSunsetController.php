<?php

namespace Jarryd\Sunset\Tests\Stubs;

use Jarryd\Sunset\Sunset;

#[Sunset('2027-06-30', mode: 'enforce')]
class ClassSunsetController
{
    public function index(): string
    {
        return 'index';
    }

    #[Sunset('2027-01-15')]
    public function earlier(): string
    {
        return 'earlier';
    }
}
