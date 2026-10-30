<?php

namespace Jarryd\Sunset\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Jarryd\Sunset\SunsetServiceProvider;
use Jarryd\Sunset\Support\SunsetReader;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;

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

        // Set DB_CONNECTION=mysql|pgsql (plus DB_HOST, DB_DATABASE, ...) to run
        // the suite against a real server; Testbench defaults to in-memory SQLite.
        $connection = getenv('DB_CONNECTION');

        if (in_array($connection, ['mysql', 'mariadb', 'pgsql'], true)) {
            $app['config']->set('database.default', $connection);
        }

        // The web group encrypts cookies, which needs a key.
        $app['config']->set('app.key', 'base64:SX8xwpOBOv8BORUEBzL+PypoyKj6xcjOUKJvvpkvvI0=');
    }

    /**
     * Run a command with --json and decode its output. It's captured on its own
     * buffer: a lazy database refresh inside the command would reset Artisan::output().
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    protected function artisanJson(string $command, array $parameters = []): array
    {
        $output = new BufferedOutput;
        Artisan::call($command, $parameters + ['--json' => true], $output);

        return json_decode($output->fetch(), true);
    }

    protected function getPackageProviders($app): array
    {
        // The integrations CI job installs Route Notes and Ghost Buster; every
        // other job runs without them.
        return array_filter([
            SunsetServiceProvider::class,
            'Jarryd\RouteNotes\RouteNotesServiceProvider',
            'Jarryd\GhostBuster\GhostBusterServiceProvider',
        ], fn (string $provider) => class_exists($provider));
    }
}
