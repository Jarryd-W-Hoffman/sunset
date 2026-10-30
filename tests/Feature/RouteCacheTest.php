<?php

namespace Jarryd\Sunset\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Mode;
use Jarryd\Sunset\Support\SunsetReader;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteCacheTest extends TestCase
{
    #[Test]
    public function sunsets_survive_route_caching()
    {
        $this->defineCacheRoutes(<<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Tests\Stubs\ClassSunsetController;
use Jarryd\Sunset\Tests\Stubs\ParameterController;

Route::get('/cached/{id}', [ParameterController::class, 'show'])
    ->name('cached.macro')
    ->sunset('2027-03-01', replacement: 'reports.index', since: '2026-11-01', reason: 'Use the new reports', mode: 'enforce');

Route::get('/cached-attribute', [ClassSunsetController::class, 'index'])->name('cached.attribute');
PHP);

        $this->assertTrue($this->app->routesAreCached());

        $macro = SunsetReader::forRoute(Route::getRoutes()->getByName('cached.macro'));

        $this->assertNotNull($macro);

        $this->assertSame('2027-03-01', $macro->date);
        $this->assertSame('reports.index', $macro->replacement);
        $this->assertSame('2026-11-01', $macro->since);
        $this->assertSame('Use the new reports', $macro->reason);
        $this->assertSame(Mode::Enforce, $macro->mode);

        $this->assertSame('2027-06-30', SunsetReader::forRoute(Route::getRoutes()->getByName('cached.attribute'))?->date);
    }

    #[Test]
    public function cached_routes_do_not_receive_the_sunset_as_a_parameter()
    {
        $this->defineCacheRoutes(<<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Tests\Stubs\ParameterController;

Route::get('/cached/{id}', [ParameterController::class, 'show'])->sunset('2027-03-01');
PHP);

        $this->getJson('/cached/5')
            ->assertOk()
            ->assertExactJson(['parameters' => ['id' => '5'], 'id' => '5']);
    }
}
