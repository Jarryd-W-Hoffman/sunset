<?php

namespace Jarryd\Sunset\Support;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jarryd\Sunset\Stage;
use Jarryd\Sunset\Sunset;

class SunsetRoutes
{
    public function __construct(protected Router $router) {}

    /**
     * Every route with a sunset, soonest first, with its calls summarised.
     *
     * @return Collection<int, array{route: Route, key: string, sunset: Sunset, stage: Stage, callers: int, last_called_at: CarbonImmutable|null}>
     */
    public function all(): Collection
    {
        // Routes named after they were added aren't in the name lookup until it's
        // refreshed, so replacements could look missing without this.
        $this->router->getRoutes()->refreshNameLookups();

        $quietSince = now()->subDays((int) config('sunset.quiet_days', 30));

        // Keyed by method too, so unnamed routes sharing a URI (GET and POST
        // /orders) don't share calls.
        $calls = DB::table('sunset_calls')
            ->get(['route', 'method', 'caller', 'last_called_at'])
            ->groupBy(fn (object $call) => "{$call->method} {$call->route}");

        return collect($this->router->getRoutes()->getRoutes())
            ->map(function (Route $route) use ($calls, $quietSince) {
                $sunset = SunsetReader::forRoute($route);

                if ($sunset === null) {
                    return null;
                }

                $key = static::key($route);
                $routeCalls = collect($route->methods())->flatMap(fn (string $method) => $calls->get("{$method} {$key}", []));
                $lastCalled = $routeCalls->max('last_called_at');
                $lastCalledAt = is_string($lastCalled) ? CarbonImmutable::parse($lastCalled) : null;
                $callers = $routeCalls
                    ->filter(fn (object $call) => CarbonImmutable::parse($call->last_called_at)->gte($quietSince))
                    ->pluck('caller')
                    ->unique()
                    ->count();

                return [
                    'route' => $route,
                    'key' => $key,
                    'sunset' => $sunset,
                    'stage' => Stage::for($sunset, $lastCalledAt),
                    'callers' => $callers,
                    'last_called_at' => $lastCalledAt,
                ];
            })
            ->filter()
            ->sortBy(fn (array $entry) => $entry['sunset']->date()->getTimestamp())
            ->values();
    }

    /**
     * How a route is identified in sunset_calls: its name, or its URI.
     */
    public static function key(Route $route): string
    {
        return $route->getName() ?? $route->uri();
    }
}
