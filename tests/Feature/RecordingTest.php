<?php

namespace Jarryd\Sunset\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Events\DeprecatedRouteCalled;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RecordingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/reports/legacy', fn () => 'Legacy')->name('reports.legacy')->sunset('2027-06-30');
        Route::post('/orders', fn () => 'Created')->sunset('2027-06-30');
        Route::get('/reports', fn () => 'Reports')->name('reports.index');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function calls(): array
    {
        return DB::table('sunset_calls')
            ->orderBy('route')->orderBy('caller')
            ->get(['route', 'method', 'caller', 'ip', 'hits', 'first_called_at', 'last_called_at'])
            ->map(fn (object $call) => (array) $call)
            ->all();
    }

    #[Test]
    public function it_records_each_caller_with_a_running_hit_count()
    {
        $this->get('/reports/legacy', ['User-Agent' => 'okhttp/4.9.3']);
        $this->travel(2)->hours();
        $this->get('/reports/legacy', ['User-Agent' => 'okhttp/4.12.0']);
        $this->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);

        $this->assertSame([
            [
                'route' => 'reports.legacy', 'method' => 'GET', 'caller' => 'agent:curl', 'ip' => null, 'hits' => 1,
                'first_called_at' => '2026-10-30 11:00:00', 'last_called_at' => '2026-10-30 11:00:00',
            ],
            [
                'route' => 'reports.legacy', 'method' => 'GET', 'caller' => 'agent:okhttp', 'ip' => null, 'hits' => 2,
                'first_called_at' => '2026-10-30 09:00:00', 'last_called_at' => '2026-10-30 11:00:00',
            ],
        ], $this->calls());
    }

    #[Test]
    public function unnamed_routes_are_recorded_by_uri()
    {
        $this->post('/orders', [], ['User-Agent' => 'curl/8.5.0']);

        $this->assertDatabaseHas('sunset_calls', ['route' => 'orders', 'method' => 'POST', 'caller' => 'agent:curl']);
    }

    #[Test]
    public function routes_without_a_sunset_are_not_recorded()
    {
        $this->get('/reports');

        $this->assertDatabaseCount('sunset_calls', 0);
    }

    #[Test]
    public function blocked_calls_are_recorded_too()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelTo(CarbonImmutable::parse('2027-07-01 08:00:00'));

        $this->postJson('/orders', [], ['User-Agent' => 'curl/8.5.0'])->assertStatus(410);

        $this->assertDatabaseHas('sunset_calls', ['route' => 'orders', 'caller' => 'agent:curl', 'hits' => 1]);
    }

    #[Test]
    public function recording_can_be_turned_off()
    {
        config(['sunset.record_calls' => false]);

        $this->get('/reports/legacy')->assertOk();

        $this->assertDatabaseCount('sunset_calls', 0);
    }

    #[Test]
    public function ip_addresses_are_only_recorded_when_you_opt_in()
    {
        $this->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);
        $this->assertDatabaseHas('sunset_calls', ['caller' => 'agent:curl', 'ip' => null]);

        config(['sunset.record_ip' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);
        $this->assertDatabaseHas('sunset_calls', ['caller' => 'agent:curl', 'ip' => '203.0.113.9', 'hits' => 2]);
    }

    #[Test]
    public function the_event_fires_once_per_caller_route_and_method_each_day()
    {
        Event::fake([DeprecatedRouteCalled::class]);

        $this->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);
        $this->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);
        $this->get('/reports/legacy', ['User-Agent' => 'okhttp/4.9.3']);
        $this->post('/orders', [], ['User-Agent' => 'curl/8.5.0']);

        Event::assertDispatchedTimes(DeprecatedRouteCalled::class, 3);

        $this->travelTo(CarbonImmutable::parse('2026-10-31 00:00:01'));
        $this->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);

        Event::assertDispatchedTimes(DeprecatedRouteCalled::class, 4);
    }

    #[Test]
    public function the_event_describes_the_call()
    {
        Event::fake([DeprecatedRouteCalled::class]);

        $this->get('/reports/legacy', ['User-Agent' => 'curl/8.5.0']);

        Event::assertDispatched(DeprecatedRouteCalled::class, fn (DeprecatedRouteCalled $event) => $event->route === 'reports.legacy'
            && $event->method === 'GET'
            && $event->caller === 'agent:curl'
            && $event->sunset->date === '2027-06-30'
            && $event->pastSunset === false);
    }

    #[Test]
    public function the_event_says_when_a_call_is_past_the_sunset_date()
    {
        Event::fake([DeprecatedRouteCalled::class]);
        $this->travelTo(CarbonImmutable::parse('2027-07-01 08:00:00'));

        $this->get('/reports/legacy');

        Event::assertDispatched(DeprecatedRouteCalled::class, fn (DeprecatedRouteCalled $event) => $event->pastSunset);
    }

    #[Test]
    public function the_event_fires_even_when_recording_is_off()
    {
        Event::fake([DeprecatedRouteCalled::class]);
        config(['sunset.record_calls' => false]);

        $this->get('/reports/legacy');

        Event::assertDispatchedTimes(DeprecatedRouteCalled::class, 1);
    }
}
