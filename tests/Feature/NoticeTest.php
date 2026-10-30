<?php

namespace Jarryd\Sunset\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Jarryd\Sunset\Sunset;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NoticeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/reports', fn () => Blade::render('<x-sunset::notice class="mb-4" />Reports'))->name('reports.index');
        Route::get('/reports/legacy', fn () => Blade::render('<x-sunset::notice />Legacy'))->name('reports.legacy')
            ->sunset('2027-03-01', replacement: 'reports.index', reason: 'The new reports are faster.');
        Route::get('/promo', fn () => Blade::render('<x-sunset::notice class="mb-4" />Promo'))->sunset('2026-08-01');
        Route::get('/current', fn () => response()->json(Sunset::current()));
    }

    #[Test]
    public function the_notice_shows_on_sunset_pages()
    {
        $this->get('/reports/legacy')
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('data-sunset="2027-03-01"', false)
            ->assertSee('This page is being retired on 1 March 2027.')
            ->assertSee('The new reports are faster.')
            ->assertSee('<a href="http://localhost/reports">Use its replacement instead.</a>', false);
    }

    #[Test]
    public function the_notice_renders_nothing_elsewhere()
    {
        $response = $this->get('/reports')->assertOk();

        $this->assertSame('Reports', trim($response->getContent()));
    }

    #[Test]
    public function the_notice_says_when_a_page_was_retired()
    {
        $this->get('/promo')
            ->assertOk()
            ->assertSee('This page was retired on 1 August 2026.')
            ->assertSee('class="sunset-notice mb-4"', false)
            ->assertDontSee('Use its replacement instead.');
    }

    #[Test]
    public function the_notice_view_can_be_swapped()
    {
        View::addNamespace('app', __DIR__.'/../Stubs/views');
        config(['sunset.notice_view' => 'app::custom-notice']);

        $this->get('/reports/legacy')->assertSeeText('Custom: 2027-03-01 → http://localhost/reports');
    }

    #[Test]
    public function current_returns_the_notice_as_plain_data()
    {
        Route::get('/api/orders', fn () => response()->json(Sunset::current()))->sunset('2027-01-15', replacement: '/v2/orders');

        $this->getJson('/api/orders')->assertExactJson([
            'sunset' => '2027-01-15',
            'message' => 'This page is being retired on 15 January 2027.',
            'reason' => null,
            'replacement' => 'http://localhost/v2/orders',
            'past_sunset' => false,
        ]);
    }

    #[Test]
    public function current_is_null_without_a_sunset()
    {
        $this->get('/current')->assertOk()->assertContent('{}');
    }

    #[Test]
    public function current_is_null_outside_a_request()
    {
        $this->assertNull(Sunset::current());
    }

    #[Test]
    public function the_notice_shows_after_the_date_in_warn_mode()
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-02 09:00:00'));

        $this->get('/reports/legacy')->assertOk()->assertSee('This page was retired on 1 March 2027.');
    }
}
