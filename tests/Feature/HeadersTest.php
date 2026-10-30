<?php

namespace Jarryd\Sunset\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Jarryd\Sunset\Tests\Stubs\ClassSunsetController;
use Jarryd\Sunset\Tests\Stubs\InvokableSunsetController;
use Jarryd\Sunset\Tests\Stubs\MethodSunsetController;
use Jarryd\Sunset\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class HeadersTest extends TestCase
{
    #[Test]
    public function it_sends_the_sunset_date_as_an_http_date()
    {
        Route::get('/legacy', fn () => 'OK')->sunset('2027-06-30');

        $this->get('/legacy')
            ->assertOk()
            ->assertSeeText('OK')
            ->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT');
    }

    #[Test]
    public function the_sunset_is_midnight_in_the_app_timezone()
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Australia/Sydney');

        try {
            Route::get('/legacy', fn () => 'OK')->sunset('2027-03-01');

            // Midnight on 1 March in Sydney (UTC+11 in summer) is 1pm on 28 February in GMT.
            $this->get('/legacy')->assertHeader('Sunset', 'Sun, 28 Feb 2027 13:00:00 GMT');
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    #[Test]
    public function it_sends_the_deprecation_date_when_since_is_given()
    {
        Route::get('/legacy', fn () => 'OK')->sunset('2027-06-30', since: '2026-11-01');

        $this->get('/legacy')->assertHeader('Deprecation', '@1793491200');
    }

    #[Test]
    public function it_leaves_out_the_deprecation_header_without_since()
    {
        Route::get('/legacy', fn () => 'OK')->sunset('2027-06-30');

        $this->get('/legacy')->assertHeaderMissing('Deprecation');
    }

    #[Test]
    public function it_links_to_a_replacement_route()
    {
        Route::get('/reports', fn () => 'OK')->name('reports.index');
        Route::get('/reports/legacy', fn () => 'OK')->sunset('2027-06-30', replacement: 'reports.index');

        $this->get('/reports/legacy')->assertHeader('Link', '<http://localhost/reports>; rel="successor-version"');
    }

    #[Test]
    public function it_reuses_the_current_route_parameters_for_the_replacement()
    {
        Route::get('/blog/{post}', fn () => 'OK')->name('blog.show');
        Route::get('/articles/{post}', fn () => 'OK')->sunset('2027-06-30', replacement: 'blog.show');

        $this->get('/articles/hello-world')->assertHeader('Link', '<http://localhost/blog/hello-world>; rel="successor-version"');
    }

    #[Test]
    public function optional_replacement_parameters_can_be_missing()
    {
        Route::get('/docs/{page?}', fn () => 'OK')->name('docs');
        Route::get('/manual', fn () => 'OK')->sunset('2027-06-30', replacement: 'docs');

        $this->get('/manual')->assertHeader('Link', '<http://localhost/docs>; rel="successor-version"');
    }

    #[Test]
    public function it_links_to_a_replacement_url_or_path()
    {
        Route::get('/old-docs', fn () => 'OK')->sunset('2027-06-30', replacement: 'https://docs.acme.test/v2');
        Route::get('/old-help', fn () => 'OK')->sunset('2027-06-30', replacement: '/help');

        $this->get('/old-docs')->assertHeader('Link', '<https://docs.acme.test/v2>; rel="successor-version"');
        $this->get('/old-help')->assertHeader('Link', '<http://localhost/help>; rel="successor-version"');
    }

    #[Test]
    public function an_unresolvable_replacement_is_left_out_without_breaking_the_response()
    {
        Route::get('/blog/{post}', fn () => 'OK')->name('blog.show');
        Route::get('/typo', fn () => 'OK')->sunset('2027-06-30', replacement: 'reprots.index');
        Route::get('/no-post', fn () => 'OK')->sunset('2027-06-30', replacement: 'blog.show');

        $this->get('/typo')->assertOk()->assertHeader('Sunset')->assertHeaderMissing('Link');
        $this->get('/no-post')->assertOk()->assertHeader('Sunset')->assertHeaderMissing('Link');
    }

    #[Test]
    public function routes_without_a_sunset_get_no_headers()
    {
        Route::get('/current', fn () => 'OK')->middleware('web');

        $this->get('/current')
            ->assertOk()
            ->assertHeaderMissing('Sunset')
            ->assertHeaderMissing('Deprecation')
            ->assertHeaderMissing('Link');
    }

    #[Test]
    public function attribute_routes_in_the_web_and_api_groups_get_headers()
    {
        Route::get('/legacy', [MethodSunsetController::class, 'legacy'])->middleware('web');
        Route::get('/api/report', InvokableSunsetController::class)->middleware('api');

        $this->get('/legacy')->assertHeader('Sunset', 'Mon, 01 Mar 2027 00:00:00 GMT');
        $this->get('/api/report')
            ->assertHeader('Sunset', 'Wed, 01 Sep 2027 00:00:00 GMT')
            ->assertHeader('Deprecation', '@1793491200');
    }

    #[Test]
    public function attribute_routes_outside_the_groups_need_the_sunset_alias()
    {
        Route::get('/bare', [ClassSunsetController::class, 'index']);
        Route::get('/aliased', [ClassSunsetController::class, 'index'])->middleware('sunset');

        $this->get('/bare')->assertOk()->assertHeaderMissing('Sunset');
        $this->get('/aliased')->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT');
    }

    #[Test]
    public function headers_are_added_once_when_the_group_adds_the_middleware_too()
    {
        Route::get('/reports', fn () => 'OK')->name('reports.index');
        Route::get('/legacy', fn () => 'OK')->middleware('web')->sunset('2027-06-30', replacement: 'reports.index');

        $response = $this->get('/legacy');

        $this->assertCount(1, $response->headers->all('link'));
        $this->assertCount(1, $response->headers->all('sunset'));
    }

    #[Test]
    public function headers_are_added_once_when_the_alias_is_used_as_well()
    {
        Route::get('/reports', fn () => 'OK')->name('reports.index');
        Route::get('/legacy', fn () => 'OK')->middleware(['web', 'sunset'])->sunset('2027-06-30', replacement: 'reports.index');

        $this->assertCount(1, $this->get('/legacy')->headers->all('link'));
    }

    #[Test]
    public function headers_the_app_sets_itself_are_kept()
    {
        Route::get('/reports', fn () => 'OK')->name('reports.index');
        Route::get('/legacy', fn () => response('OK')->withHeaders([
            'Sunset' => 'Fri, 01 Jan 2027 00:00:00 GMT',
            'Deprecation' => '@1700000000',
            'Link' => '</app.css>; rel="preload"; as="style"',
        ]))->sunset('2027-06-30', since: '2026-11-01', replacement: 'reports.index');

        $response = $this->get('/legacy')
            ->assertHeader('Sunset', 'Fri, 01 Jan 2027 00:00:00 GMT')
            ->assertHeader('Deprecation', '@1700000000');

        $this->assertSame([
            '</app.css>; rel="preload"; as="style"',
            '<http://localhost/reports>; rel="successor-version"',
        ], $response->headers->all('link'));
    }

    #[Test]
    public function error_responses_from_sunset_routes_get_headers_too()
    {
        Route::post('/legacy', fn () => response()->json(['message' => 'Invalid'], 422))->sunset('2027-06-30');

        $this->postJson('/legacy')->assertStatus(422)->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT');
    }

    #[Test]
    #[DefineEnvironment('onlyTheApiGroup')]
    public function only_the_configured_groups_get_the_middleware()
    {
        Route::get('/web', [ClassSunsetController::class, 'index'])->middleware('web');
        Route::get('/api', [ClassSunsetController::class, 'index'])->middleware('api');

        $this->get('/web')->assertHeaderMissing('Sunset');
        $this->get('/api')->assertHeader('Sunset');
    }

    #[Test]
    #[DefineEnvironment('unknownGroups')]
    public function groups_the_app_does_not_define_are_skipped()
    {
        Route::get('/legacy', fn () => 'OK')->sunset('2027-06-30');

        $this->get('/legacy')->assertOk()->assertHeader('Sunset');
    }

    protected function onlyTheApiGroup($app): void
    {
        $app['config']->set('sunset.middleware_groups', ['api']);
    }

    protected function unknownGroups($app): void
    {
        $app['config']->set('sunset.middleware_groups', ['web', 'admin', 'partner-api']);
    }
}
