<?php

namespace Jarryd\Sunset\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Integrations\GhostBuster;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GhostBusterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('Jarryd\GhostBuster\GhostBusterServiceProvider')) {
            $this->markTestSkipped('Ghost Buster is not installed.');
        }

        Route::get('/reports/legacy', fn () => 'OK')->name('reports.legacy')->sunset('2027-06-30');
        Route::get('/orders', fn () => 'OK')->sunset('2027-01-15');
        Route::post('/orders', fn () => 'OK')->sunset('2027-01-15');

        DB::table('route_usage')->insert([
            ['route_name' => 'reports.legacy', 'method' => 'GET', 'hits' => 1200, 'last_used_at' => '2026-10-29 10:00:00'],
            ['route_name' => 'reports.legacy', 'method' => 'HEAD', 'hits' => 40, 'last_used_at' => '2026-10-30 08:00:00'],
            ['route_name' => 'orders', 'method' => 'POST', 'hits' => 7, 'last_used_at' => '2026-09-01 10:00:00'],
        ]);
    }

    #[Test]
    public function the_list_includes_ghost_buster_usage_per_route_and_method()
    {
        $this->assertTrue(GhostBuster::installed());

        $this->assertSame([
            'orders GET,HEAD' => ['hits' => 0, 'last_used_at' => null],
            'orders POST' => ['hits' => 7, 'last_used_at' => '2026-09-01T10:00:00+00:00'],
            'reports.legacy GET,HEAD' => ['hits' => 1240, 'last_used_at' => '2026-10-30T08:00:00+00:00'],
        ], collect($this->artisanJson('sunset:list'))
            ->mapWithKeys(fn (array $row) => [$row['route'].' '.implode(',', $row['methods']) => $row['ghost_buster']])
            ->all());
    }

    #[Test]
    public function the_table_shows_last_used_and_hits()
    {
        $this->artisan('sunset:list')
            ->expectsOutputToContain('Never')
            ->expectsOutputToContain('1,240')
            ->expectsOutputToContain('Last used and Hits: from Ghost Buster, as of its last sync.')
            ->assertExitCode(0);
    }
}
