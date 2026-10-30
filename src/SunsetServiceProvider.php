<?php

namespace Jarryd\Sunset;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

class SunsetServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sunset.php', 'sunset');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sunset.php' => config_path('sunset.php'),
            ], 'sunset-config');
        }

        // Sunsets live in the route action rather than the route defaults, as
        // defaults are merged into the route parameters at dispatch time.
        // The sunset is validated here, so a bad date fails when routes load.
        Route::macro('sunset', function (
            string $date,
            ?string $replacement = null,
            ?string $since = null,
            ?string $reason = null,
            Mode|string|null $mode = null,
        ) {
            /** @var Route $this */
            $this->action['sunset'] = (new Sunset($date, $replacement, $since, $reason, $mode))->toArray();

            return $this;
        });
    }
}
