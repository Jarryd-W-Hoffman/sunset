<?php

namespace Jarryd\Sunset\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Integrations\GhostBuster;
use Jarryd\Sunset\Integrations\RouteNotes;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Sunset on its own: none of the Route Notes or Ghost Buster extras appear.
 */
class WithoutIntegrationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (RouteNotes::installed() || class_exists('Jarryd\GhostBuster\GhostBusterServiceProvider')) {
            $this->markTestSkipped('Route Notes or Ghost Buster is installed.');
        }

        Route::get('/reports/legacy', fn () => 'OK')->name('reports.legacy')->sunset('2027-06-30');
    }

    #[Test]
    public function sunset_adds_no_notes()
    {
        $this->assertArrayNotHasKey('notes', tap(Route::getRoutes())->refreshNameLookups()->getByName('reports.legacy')->getAction());
    }

    #[Test]
    public function candidates_explains_that_it_needs_route_notes()
    {
        $this->artisan('sunset:list', ['--candidates' => true])
            ->expectsOutput('The --candidates option needs jarryd/route-notes: composer require jarryd/route-notes')
            ->assertExitCode(2);
    }

    #[Test]
    public function the_list_has_no_ghost_buster_usage()
    {
        $this->assertFalse(GhostBuster::installed());

        $this->assertArrayNotHasKey('ghost_buster', $this->artisanJson('sunset:list')[0]);

        $this->artisan('sunset:list')->doesntExpectOutputToContain('Last used')->assertExitCode(0);
    }
}
