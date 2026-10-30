<?php

namespace Jarryd\Sunset\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/reports', fn () => 'OK')->name('reports.index');
        Route::get('/reports/legacy', fn () => 'OK')->name('reports.legacy')
            ->sunset('2027-06-30', replacement: 'reports.index', reason: 'Use the new reports', mode: 'enforce');
        Route::get('/promo', fn () => 'OK')->name('promo')->sunset('2026-08-01', since: '2026-06-01');
        Route::get('/orders', fn () => 'OK')->sunset('2027-01-15', replacement: '/v2/orders');
        Route::post('/orders', fn () => 'OK')->sunset('2027-01-15');
    }

    private function recordCall(string $route, string $method, string $caller, int $hits, string $last, string $first = '2026-09-01 10:00:00', ?string $ip = null): void
    {
        DB::table('sunset_calls')->insert([
            'route' => $route, 'method' => $method, 'caller' => $caller, 'ip' => $ip, 'hits' => $hits,
            'first_called_at' => $first, 'last_called_at' => $last,
        ]);
    }

    #[Test]
    public function list_shows_every_sunset_route_soonest_first()
    {
        $this->recordCall('reports.legacy', 'GET', 'token:mobile-app', 40, '2026-10-29 10:00:00');
        $this->recordCall('reports.legacy', 'GET', 'agent:curl', 3, '2026-10-20 10:00:00');
        $this->recordCall('reports.legacy', 'GET', 'user:9', 1, '2026-08-01 10:00:00');

        $this->assertSame([
            [
                'route' => 'promo', 'methods' => ['GET', 'HEAD'], 'sunset' => '2026-08-01', 'since' => '2026-06-01',
                'stage' => 'safe to delete', 'mode' => 'warn', 'replacement' => null, 'reason' => null,
                'callers' => 0, 'last_called_at' => null,
            ],
            [
                'route' => 'orders', 'methods' => ['GET', 'HEAD'], 'sunset' => '2027-01-15', 'since' => null,
                'stage' => 'deprecated', 'mode' => 'warn', 'replacement' => '/v2/orders', 'reason' => null,
                'callers' => 0, 'last_called_at' => null,
            ],
            [
                'route' => 'orders', 'methods' => ['POST'], 'sunset' => '2027-01-15', 'since' => null,
                'stage' => 'deprecated', 'mode' => 'warn', 'replacement' => null, 'reason' => null,
                'callers' => 0, 'last_called_at' => null,
            ],
            [
                'route' => 'reports.legacy', 'methods' => ['GET', 'HEAD'], 'sunset' => '2027-06-30', 'since' => null,
                'stage' => 'deprecated', 'mode' => 'enforce', 'replacement' => 'reports.index', 'reason' => 'Use the new reports',
                'callers' => 2, 'last_called_at' => '2026-10-29T10:00:00+00:00',
            ],
        ], array_map(
            // The integrations job adds Ghost Buster's usage, covered in GhostBusterTest.
            fn (array $route) => Arr::except($route, 'ghost_buster'),
            $this->artisanJson('sunset:list'),
        ));
    }

    #[Test]
    public function list_keeps_calls_to_unnamed_routes_apart_by_method()
    {
        $this->recordCall('orders', 'POST', 'agent:curl', 5, '2026-10-29 10:00:00');

        $orders = collect($this->artisanJson('sunset:list'))->where('route', 'orders')->pluck('callers', 'methods.0')->all();

        $this->assertSame(['GET' => 0, 'POST' => 1], $orders);
    }

    #[Test]
    public function list_shows_a_table()
    {
        $this->artisan('sunset:list')
            // One expectation per line, in table order: each matches a separate line.
            ->expectsOutputToContain('Safe to delete')
            ->expectsOutputToContain('/v2/orders')
            ->expectsOutputToContain('30 Jun 2027')
            ->expectsOutputToContain('Callers: distinct callers in the last 30 days.')
            ->assertExitCode(0);
    }

    #[Test]
    public function list_says_when_there_are_no_sunset_routes()
    {
        Route::setRoutes(new RouteCollection);

        $this->artisan('sunset:list')->expectsOutput('No routes have a sunset date.')->assertExitCode(0);
    }

    #[Test]
    public function report_lists_callers_busiest_first()
    {
        $this->recordCall('reports.legacy', 'GET', 'agent:curl', 3, '2026-10-20 10:00:00');
        $this->recordCall('reports.legacy', 'GET', 'token:mobile-app', 40, '2026-10-29 10:00:00', '2026-07-01 08:00:00');
        $this->recordCall('orders', 'POST', 'user:9', 12, '2026-09-01 10:00:00');

        $this->assertSame([
            [
                'route' => 'reports.legacy', 'method' => 'GET', 'caller' => 'token:mobile-app', 'ip' => null, 'hits' => 40,
                'first_called_at' => '2026-07-01T08:00:00+00:00', 'last_called_at' => '2026-10-29T10:00:00+00:00',
            ],
            [
                'route' => 'orders', 'method' => 'POST', 'caller' => 'user:9', 'ip' => null, 'hits' => 12,
                'first_called_at' => '2026-09-01T10:00:00+00:00', 'last_called_at' => '2026-09-01T10:00:00+00:00',
            ],
            [
                'route' => 'reports.legacy', 'method' => 'GET', 'caller' => 'agent:curl', 'ip' => null, 'hits' => 3,
                'first_called_at' => '2026-09-01T10:00:00+00:00', 'last_called_at' => '2026-10-20T10:00:00+00:00',
            ],
        ], $this->artisanJson('sunset:report'));
    }

    #[Test]
    public function report_filters_by_route_and_recency()
    {
        $this->recordCall('reports.legacy', 'GET', 'agent:curl', 3, '2026-10-20 10:00:00');
        $this->recordCall('reports.legacy', 'GET', 'token:mobile-app', 40, '2026-10-29 10:00:00');
        $this->recordCall('orders', 'POST', 'user:9', 12, '2026-10-29 10:00:00');

        $this->assertSame(['token:mobile-app', 'agent:curl'], array_column($this->artisanJson('sunset:report', ['route' => 'reports.legacy']), 'caller'));
        $this->assertSame(['token:mobile-app', 'user:9'], array_column($this->artisanJson('sunset:report', ['--since' => 7]), 'caller'));
    }

    #[Test]
    public function report_shows_a_table_with_ips_only_when_recorded()
    {
        $this->recordCall('reports.legacy', 'GET', 'token:mobile-app', 1240, '2026-10-29 10:00:00');

        $this->artisan('sunset:report')
            ->expectsTable(['Route', 'Method', 'Caller', 'Hits', 'Last called', 'First called'], [
                ['reports.legacy', 'GET', 'token:mobile-app', '1,240', '23 hours ago', '1 Sep 2026'],
            ])
            ->assertExitCode(0);

        $this->recordCall('orders', 'POST', 'agent:curl', 2, '2026-10-29 10:00:00', ip: '203.0.113.9');

        $this->artisan('sunset:report')->expectsOutputToContain('203.0.113.9')->assertExitCode(0);
    }

    #[Test]
    public function report_rejects_an_invalid_since()
    {
        $this->artisan('sunset:report', ['--since' => 'week'])
            ->expectsOutput('The --since option must be a whole number of days, 1 or more.')
            ->assertExitCode(2);

        $this->artisan('sunset:report', ['--since' => 0])->assertExitCode(2);
    }

    #[Test]
    public function report_says_when_nothing_has_been_recorded()
    {
        $this->artisan('sunset:report')->expectsOutput('No calls to sunset routes have been recorded.')->assertExitCode(0);
        $this->artisan('sunset:report', ['--since' => 7])->expectsOutput('No calls to sunset routes have been recorded in the last 7 days.');
    }

    #[Test]
    public function check_fails_on_a_route_that_is_safe_to_delete()
    {
        $this->artisan('sunset:check')
            ->expectsOutput('Found 1 problem:')
            ->expectsOutput('  - promo: retired on 1 Aug 2026 and quiet for 30 days. It\'s safe to delete.')
            ->assertExitCode(1);
    }

    #[Test]
    public function check_fails_on_a_replacement_that_does_not_exist()
    {
        Route::get('/promo', fn () => 'OK')->name('promo')->sunset('2027-08-01');
        Route::get('/typo', fn () => 'OK')->sunset('2027-08-01', replacement: 'reprots.index');

        $this->artisan('sunset:check')
            ->expectsOutput('Found 1 problem:')
            ->expectsOutput("  - typo: its replacement [reprots.index] isn't a route name, URL or path.")
            ->assertExitCode(1);
    }

    #[Test]
    public function check_passes_when_everything_looks_good()
    {
        // A recent call keeps the retired promo route from being safe to delete.
        $this->recordCall('promo', 'GET', 'agent:curl', 1, '2026-10-29 10:00:00');

        $this->artisan('sunset:check')->expectsOutput('All 4 sunset routes look good.')->assertExitCode(0);
    }

    #[Test]
    public function routes_can_be_checked_before_any_call_is_recorded()
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-01 09:00:00'));

        $this->artisan('sunset:check')->assertExitCode(0);
    }
}
