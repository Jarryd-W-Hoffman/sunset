<?php

namespace Jarryd\Sunset\Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Integrations\RouteNotes;
use Jarryd\Sunset\Tests\Stubs\LegacyReportsController;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteNotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! RouteNotes::installed()) {
            $this->markTestSkipped('Route Notes is not installed.');
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function notes(string $name): array
    {
        return tap(Route::getRoutes())->refreshNameLookups()->getByName($name)->getAction('notes') ?? [];
    }

    #[Test]
    public function sunset_adds_a_deprecated_note()
    {
        Route::get('/reports/legacy', fn () => 'OK')->name('reports.legacy')
            ->note('Used by the admin portal only')
            ->sunset('2027-03-01', replacement: 'reports.index');

        $this->assertSame([
            ['text' => 'Used by the admin portal only', 'level' => 'info'],
            ['text' => 'Sunset on 1 Mar 2027, use reports.index', 'level' => 'deprecated'],
        ], $this->notes('reports.legacy'));
    }

    #[Test]
    public function a_new_sunset_replaces_the_old_note()
    {
        Route::get('/promo', fn () => 'OK')->name('promo')->sunset('2027-03-01')->sunset('2027-05-01');

        $this->assertSame([['text' => 'Sunset on 1 May 2027', 'level' => 'deprecated']], $this->notes('promo'));
    }

    #[Test]
    public function the_note_shows_in_route_list()
    {
        Route::get('/reports/legacy', fn () => 'OK')->name('reports.legacy')->sunset('2027-03-01', replacement: 'reports.index');

        Artisan::call('route:list', ['--path' => 'reports']);

        $this->assertStringContainsString('Sunset on 1 Mar 2027, use reports.index', Artisan::output());
    }

    #[Test]
    public function candidates_lists_deprecated_routes_without_a_sunset_date()
    {
        Route::get('/reports/legacy', [LegacyReportsController::class, 'index'])->name('reports.legacy');
        Route::get('/reports/legacy/{id}', [LegacyReportsController::class, 'show'])->name('reports.legacy.show');
        Route::get('/orders', fn () => 'OK')->note('Use /v2/orders', 'deprecated');
        Route::get('/promo', fn () => 'OK')->name('promo')->note('Old campaign', 'deprecated')->sunset('2027-03-01');
        Route::get('/admin', fn () => 'OK')->note('Admins only', 'warning');

        $this->assertSame([
            ['route' => 'reports.legacy', 'methods' => ['GET', 'HEAD'], 'notes' => ['Use reports.index, which pages its results']],
            ['route' => 'orders', 'methods' => ['GET', 'HEAD'], 'notes' => ['Use /v2/orders']],
        ], $this->artisanJson('sunset:list', ['--candidates' => true]));

        $this->artisan('sunset:list', ['--candidates' => true])
            ->expectsOutputToContain('Use /v2/orders')
            ->assertExitCode(0);
    }

    #[Test]
    public function candidates_says_when_there_are_none()
    {
        Route::get('/promo', fn () => 'OK')->sunset('2027-03-01');

        $this->artisan('sunset:list', ['--candidates' => true])
            ->expectsOutput('No routes are marked deprecated without a sunset date.')
            ->assertExitCode(0);
    }
}
