<?php

namespace Jarryd\Sunset;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Jarryd\Sunset\Commands\CheckCommand;
use Jarryd\Sunset\Commands\ListCommand;
use Jarryd\Sunset\Commands\ReportCommand;
use Jarryd\Sunset\Http\Middleware\HandleSunsetRoutes;
use Jarryd\Sunset\Integrations\RouteNotes;

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
            $this->commands([
                CheckCommand::class,
                ListCommand::class,
                ReportCommand::class,
            ]);

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sunset-migrations');

            $this->publishes([
                __DIR__.'/../config/sunset.php' => config_path('sunset.php'),
            ], 'sunset-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sunset'),
            ], 'sunset-views');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sunset');
        Blade::componentNamespace('Jarryd\\Sunset\\View\\Components', 'sunset');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerMiddleware();

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
            $sunset = new Sunset($date, $replacement, $since, $reason, $mode);
            $this->action['sunset'] = $sunset->toArray();

            if (RouteNotes::installed()) {
                RouteNotes::addNote($this, $sunset);
            }

            // The router removes the duplicate if the route's group adds it too.
            return $this->middleware(HandleSunsetRoutes::class);
        });
    }

    /**
     * Give the middleware a "sunset" alias, and add it to the configured groups.
     */
    protected function registerMiddleware(): void
    {
        $this->callAfterResolving(Router::class, function (Router $router) {
            $router->aliasMiddleware('sunset', HandleSunsetRoutes::class);
        });

        // Register via the HTTP kernel rather than the router: the kernel re-syncs
        // its groups onto the router whenever they change, which would wipe a
        // router-only registration.
        $this->callAfterResolving(HttpKernel::class, function ($kernel) {
            if (! $kernel instanceof Kernel) {
                return;
            }

            $defined = $kernel->getMiddlewareGroups();

            foreach ((array) config('sunset.middleware_groups', ['web', 'api']) as $group) {
                if (isset($defined[$group])) {
                    $kernel->appendMiddlewareToGroup($group, HandleSunsetRoutes::class);
                }
            }
        });
    }
}
