<?php

namespace Jarryd\Sunset\Tests\Feature;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Jarryd\Sunset\Mode;
use Jarryd\Sunset\Support\SunsetReader;
use Jarryd\Sunset\Tests\Stubs\MethodSunsetController;
use Jarryd\Sunset\Tests\Stubs\ParameterController;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteMacroTest extends TestCase
{
    #[Test]
    public function it_stores_the_sunset_in_the_route_action()
    {
        $route = Route::get('/reports/legacy', fn () => 'OK')
            ->sunset('2027-03-01', replacement: 'reports.index', since: '2026-11-01', reason: 'Use the new reports', mode: 'enforce');

        $this->assertSame([
            'date' => '2027-03-01',
            'replacement' => 'reports.index',
            'since' => '2026-11-01',
            'reason' => 'Use the new reports',
            'mode' => 'enforce',
        ], $route->getAction('sunset'));
    }

    #[Test]
    public function it_takes_positional_arguments_too()
    {
        $route = Route::get('/reports/legacy', fn () => 'OK')->sunset('2027-03-01', 'reports.index');

        $this->assertSame('reports.index', SunsetReader::forRoute($route)?->replacement);
    }

    #[Test]
    public function it_chains_with_other_route_methods()
    {
        Route::get('/reports/legacy', fn () => 'OK')->sunset('2027-03-01')->name('reports.legacy');

        // The name is set after the route is added, so the lookup needs refreshing.
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame('2027-03-01', SunsetReader::forRoute(Route::getRoutes()->getByName('reports.legacy'))?->date);
    }

    #[Test]
    public function the_reader_returns_the_macro_sunset()
    {
        $route = Route::get('/reports/legacy', fn () => 'OK')->sunset('2027-03-01', mode: Mode::Warn);

        $sunset = SunsetReader::forRoute($route);

        $this->assertNotNull($sunset);

        $this->assertSame('2027-03-01', $sunset->date);
        $this->assertSame(Mode::Warn, $sunset->mode);
    }

    #[Test]
    public function the_macro_wins_over_a_controller_attribute()
    {
        $route = Route::get('/reports/legacy', [MethodSunsetController::class, 'legacy'])->sunset('2028-01-01');

        $this->assertSame('2028-01-01', SunsetReader::forRoute($route)?->date);
    }

    #[Test]
    public function routes_without_a_sunset_have_none()
    {
        $this->assertNull(SunsetReader::forRoute(Route::get('/reports', fn () => 'OK')));
        $this->assertNull(SunsetReader::forRoute(Route::get('/current', [MethodSunsetController::class, 'current'])));
    }

    #[Test]
    public function a_bad_date_fails_when_the_route_is_defined()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sunset date [2027-02-30].');

        Route::get('/reports/legacy', fn () => 'OK')->sunset('2027-02-30');
    }

    #[Test]
    public function a_bad_mode_fails_when_the_route_is_defined()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sunset mode [off].');

        Route::get('/reports/legacy', fn () => 'OK')->sunset('2027-03-01', mode: 'off');
    }

    #[Test]
    public function the_sunset_is_not_passed_to_the_controller_as_a_parameter()
    {
        Route::get('/orders/{id}', [ParameterController::class, 'show'])->sunset('2027-03-01');

        $this->getJson('/orders/5')
            ->assertOk()
            ->assertExactJson(['parameters' => ['id' => '5'], 'id' => '5']);
    }
}
