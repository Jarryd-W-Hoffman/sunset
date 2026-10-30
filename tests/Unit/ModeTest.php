<?php

namespace Jarryd\Sunset\Tests\Unit;

use InvalidArgumentException;
use Jarryd\Sunset\Mode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ModeTest extends TestCase
{
    #[Test]
    public function it_resolves_modes_by_name_ignoring_case()
    {
        $this->assertSame(Mode::Warn, Mode::fromName('warn'));
        $this->assertSame(Mode::Enforce, Mode::fromName('ENFORCE'));
    }

    #[Test]
    public function it_passes_modes_through()
    {
        $this->assertSame(Mode::Enforce, Mode::fromName(Mode::Enforce));
    }

    #[Test]
    public function it_fails_loudly_on_typos()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sunset mode [warning]. Expected one of: warn, enforce.');

        Mode::fromName('warning');
    }
}
