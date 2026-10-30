<?php

namespace Jarryd\Sunset\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jarryd\Sunset\SunsetServiceProvider;
use Jarryd\Sunset\Tests\Stubs\ClassSunsetController;
use Jarryd\Sunset\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class EnforcementTest extends TestCase
{
    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/reports', fn () => 'Reports')->name('reports.index');
        Route::get('/reports/legacy', function () {
            $this->calls++;

            return 'Legacy';
        })->sunset('2027-06-30', replacement: 'reports.index', reason: 'The new reports cover everything this did.');
    }

    private function travelToSunset(string $time = '2027-06-30 00:00:00'): void
    {
        $this->travelTo(CarbonImmutable::parse($time));
    }

    #[Test]
    public function routes_work_as_normal_before_their_sunset_date()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset('2027-06-29 23:59:59');

        $this->get('/reports/legacy')->assertOk()->assertSeeText('Legacy');
        $this->assertSame(1, $this->calls);
    }

    #[Test]
    public function warn_mode_keeps_serving_after_the_date_and_logs_each_call()
    {
        $log = Log::spy();
        $this->travelToSunset('2027-07-15 10:00:00');

        $this->get('/reports/legacy')
            ->assertOk()
            ->assertSeeText('Legacy')
            ->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT');

        $this->assertSame(1, $this->calls);
        $log->shouldHaveReceived('warning')->once()->with('A sunset route was called after its sunset date.', [
            'route' => 'reports/legacy',
            'method' => 'GET',
            'sunset' => '2027-06-30',
        ]);
    }

    #[Test]
    public function nothing_is_logged_before_the_date()
    {
        $log = Log::spy();

        $this->get('/reports/legacy')->assertOk();

        $log->shouldNotHaveReceived('warning');
    }

    #[Test]
    public function enforce_mode_redirects_page_requests_to_the_replacement()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        $this->get('/reports/legacy')
            ->assertStatus(301)
            ->assertRedirect('http://localhost/reports')
            ->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT')
            ->assertHeader('Link', '<http://localhost/reports>; rel="successor-version"');

        $this->assertSame(0, $this->calls);
    }

    #[Test]
    public function redirects_keep_the_query_string()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        $this->get('/reports/legacy?year=2026&format=csv')->assertRedirect('http://localhost/reports?year=2026&format=csv');
    }

    #[Test]
    public function head_requests_are_redirected_too()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        $this->call('HEAD', '/reports/legacy')->assertStatus(301);
    }

    #[Test]
    public function json_requests_get_410_with_the_replacement_instead_of_a_redirect()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        $this->getJson('/reports/legacy')
            ->assertStatus(410)
            ->assertExactJson([
                'message' => 'This endpoint was retired on 30 June 2027.',
                'reason' => 'The new reports cover everything this did.',
                'replacement' => 'http://localhost/reports',
                'sunset' => '2027-06-30',
            ])
            ->assertHeader('Link', '<http://localhost/reports>; rel="successor-version"');
    }

    #[Test]
    public function non_get_requests_are_never_redirected()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        Route::post('/orders/legacy', fn () => 'Created')->sunset('2027-06-30', replacement: '/orders');

        $this->post('/orders/legacy')->assertStatus(410)->assertSeeText('This page was retired on 30 June 2027.');
        $this->postJson('/orders/legacy')->assertStatus(410)->assertJsonPath('replacement', 'http://localhost/orders');
    }

    #[Test]
    public function pages_without_a_replacement_get_a_410_page()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        Route::get('/promo', fn () => 'Promo')->sunset('2027-06-30', reason: 'The promotion has ended.');

        $this->get('/promo')
            ->assertStatus(410)
            ->assertSeeText('This page was retired on 30 June 2027.')
            ->assertSeeText('The promotion has ended.')
            ->assertDontSeeText('Go to its replacement')
            ->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT');
    }

    #[Test]
    public function the_410_page_links_to_a_replacement_it_cannot_redirect_to()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        Route::delete('/reports/legacy/{id}', fn () => 'Deleted')->sunset('2027-06-30', replacement: 'reports.index');

        $this->delete('/reports/legacy/5')
            ->assertStatus(410)
            ->assertSee('<a href="http://localhost/reports">Go to its replacement</a>', false);
    }

    #[Test]
    public function an_unresolvable_replacement_means_410_instead_of_a_redirect()
    {
        config(['sunset.mode' => 'enforce']);
        $this->travelToSunset();

        Route::get('/typo', fn () => 'OK')->sunset('2027-06-30', replacement: 'reprots.index');

        $this->get('/typo')->assertStatus(410)->assertHeaderMissing('Link');
        $this->getJson('/typo')->assertStatus(410)->assertJsonPath('replacement', null);
    }

    #[Test]
    public function a_route_mode_wins_over_the_config()
    {
        $this->travelToSunset();

        Route::get('/strict', fn () => 'OK')->sunset('2027-06-30', mode: 'enforce');
        Route::get('/lenient', fn () => 'OK')->sunset('2027-06-30', mode: 'warn');

        config(['sunset.mode' => 'warn']);
        $this->get('/strict')->assertStatus(410);

        config(['sunset.mode' => 'enforce']);
        $this->get('/lenient')->assertOk();
    }

    #[Test]
    public function attribute_modes_are_enforced_too()
    {
        $this->travelToSunset();

        Route::get('/class', [ClassSunsetController::class, 'index'])->middleware('web');

        $this->get('/class')->assertStatus(410);
    }

    #[Test]
    public function the_grace_period_delays_enforcement()
    {
        $log = Log::spy();
        config(['sunset.mode' => 'enforce', 'sunset.grace_days' => 3]);

        $this->travelToSunset('2027-07-02 23:59:59');
        $this->get('/reports/legacy')->assertOk();
        $log->shouldHaveReceived('warning')->once();

        $this->travelToSunset('2027-07-03 00:00:00');
        $this->get('/reports/legacy')->assertStatus(301);
    }

    #[Test]
    public function enforcement_starts_at_midnight_in_the_app_timezone()
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Australia/Sydney');

        try {
            config(['sunset.mode' => 'enforce']);
            Route::get('/sydney', fn () => 'OK')->sunset('2027-03-01');

            // Midnight on 1 March in Sydney is 13:00 on 28 February in UTC.
            $this->travelTo(CarbonImmutable::parse('2027-02-28 12:59:59', 'UTC'));
            $this->get('/sydney')->assertOk();

            $this->travelTo(CarbonImmutable::parse('2027-02-28 13:00:00', 'UTC'));
            $this->get('/sydney')->assertStatus(410);
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    #[Test]
    #[DefineEnvironment('misspelledMode')]
    public function a_misspelled_config_mode_falls_back_to_warn()
    {
        $log = Log::spy();
        $this->travelToSunset();

        $this->get('/reports/legacy')->assertOk()->assertSeeText('Legacy');

        $log->shouldHaveReceived('error')->with('Invalid sunset mode [enforced]. Expected one of: warn, enforce. Falling back to warn.');
    }

    #[Test]
    public function the_410_page_can_be_published()
    {
        $this->assertFileExists(__DIR__.'/../../resources/views/gone.blade.php');
        $this->assertArrayHasKey(
            realpath(__DIR__.'/../../resources/views'),
            array_flip(array_map('realpath', array_keys(ServiceProvider::pathsToPublish(SunsetServiceProvider::class, 'sunset-views')))),
        );
    }

    protected function misspelledMode($app): void
    {
        $app['config']->set('sunset.mode', 'enforced');
    }
}
