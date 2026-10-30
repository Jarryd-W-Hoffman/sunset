<?php

namespace Jarryd\Sunset\Tests;

use Jarryd\Sunset\SunsetServiceProvider;
use Jarryd\Sunset\Support\SunsetReader;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        SunsetReader::flushCache();
    }

    protected function defineEnvironment($app): void
    {
        // Laravel 12 serves the local disk through routes, which would otherwise
        // appear alongside the routes under test.
        $app['config']->set('filesystems.disks.local.serve', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SunsetServiceProvider::class,
        ];
    }
}
