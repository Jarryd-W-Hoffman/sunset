<?php

namespace Jarryd\Sunset\Tests;

use Carbon\CarbonImmutable;
use Jarryd\Sunset\SunsetServiceProvider;
use Jarryd\Sunset\Support\SunsetReader;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        SunsetReader::flushCache();

        // The stubs' sunset dates are in 2027, so freeze time well before them;
        // otherwise the suite would change behaviour once those dates pass.
        $this->travelTo(CarbonImmutable::parse('2026-10-30 09:00:00'));
    }

    protected function defineEnvironment($app): void
    {
        // Laravel 12 serves the local disk through routes, which would otherwise
        // appear alongside the routes under test.
        $app['config']->set('filesystems.disks.local.serve', false);

        // The web group encrypts cookies, which needs a key.
        $app['config']->set('app.key', 'base64:SX8xwpOBOv8BORUEBzL+PypoyKj6xcjOUKJvvpkvvI0=');
    }

    protected function getPackageProviders($app): array
    {
        return [
            SunsetServiceProvider::class,
        ];
    }
}
