<?php

namespace Jarryd\Sunset\Tests\Unit;

use Illuminate\Routing\Route;
use Jarryd\Sunset\Mode;
use Jarryd\Sunset\Support\SunsetReader;
use Jarryd\Sunset\Tests\Stubs\ChildSunsetController;
use Jarryd\Sunset\Tests\Stubs\ClassSunsetController;
use Jarryd\Sunset\Tests\Stubs\InvokableSunsetController;
use Jarryd\Sunset\Tests\Stubs\MethodSunsetController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SunsetReaderTest extends TestCase
{
    protected function setUp(): void
    {
        SunsetReader::flushCache();
    }

    #[Test]
    public function it_reads_a_method_attribute_from_a_class_at_method_string()
    {
        $sunset = SunsetReader::forAction(MethodSunsetController::class.'@legacy');

        $this->assertNotNull($sunset);

        $this->assertSame('2027-03-01', $sunset->date);
        $this->assertSame('reports.index', $sunset->replacement);
        $this->assertSame('Use the new reports', $sunset->reason);
    }

    #[Test]
    public function it_reads_a_method_attribute_from_an_array_callable()
    {
        $this->assertSame('2027-03-01', SunsetReader::forAction([MethodSunsetController::class, 'legacy'])?->date);
    }

    #[Test]
    public function it_reads_invokable_controllers()
    {
        $sunset = SunsetReader::forAction(InvokableSunsetController::class);

        $this->assertNotNull($sunset);

        $this->assertSame('2027-09-01', $sunset->date);
        $this->assertSame('2026-11-01', $sunset->since);
    }

    #[Test]
    public function methods_without_an_attribute_have_no_sunset()
    {
        $this->assertNull(SunsetReader::forAction([MethodSunsetController::class, 'current']));
    }

    #[Test]
    public function a_class_attribute_covers_every_method()
    {
        $sunset = SunsetReader::forAction([ClassSunsetController::class, 'index']);

        $this->assertNotNull($sunset);

        $this->assertSame('2027-06-30', $sunset->date);
        $this->assertSame(Mode::Enforce, $sunset->mode);
    }

    #[Test]
    public function a_method_attribute_wins_over_the_class_attribute()
    {
        $sunset = SunsetReader::forAction([ClassSunsetController::class, 'earlier']);

        $this->assertNotNull($sunset);

        $this->assertSame('2027-01-15', $sunset->date);
        $this->assertNull($sunset->mode);
    }

    #[Test]
    public function class_attributes_are_not_inherited()
    {
        $this->assertNull(SunsetReader::forAction([ChildSunsetController::class, 'index']));
    }

    #[Test]
    public function inherited_method_attributes_still_apply()
    {
        $this->assertSame('2027-01-15', SunsetReader::forAction([ChildSunsetController::class, 'earlier'])?->date);
    }

    #[Test]
    public function closures_and_unknown_actions_have_no_sunset()
    {
        $this->assertNull(SunsetReader::forAction(fn () => 'OK'));
        $this->assertNull(SunsetReader::forAction('Missing\Controller@index'));
        $this->assertNull(SunsetReader::forAction([MethodSunsetController::class, 'missing']));
        $this->assertNull(SunsetReader::forAction(null));
        $this->assertNull(SunsetReader::forAction(['not', 'a', 'callable']));
    }

    #[Test]
    public function it_caches_what_it_reads()
    {
        $first = SunsetReader::forAction([MethodSunsetController::class, 'legacy']);

        $this->assertSame($first, SunsetReader::forAction(MethodSunsetController::class.'@legacy'));

        SunsetReader::flushCache();

        $this->assertNotSame($first, SunsetReader::forAction([MethodSunsetController::class, 'legacy']));
    }

    #[Test]
    public function it_reads_a_route_without_a_macro_sunset_from_its_controller()
    {
        $route = new Route(['GET'], 'reports/legacy', [MethodSunsetController::class, 'legacy']);

        $this->assertSame('2027-03-01', SunsetReader::forRoute($route)?->date);
    }
}
