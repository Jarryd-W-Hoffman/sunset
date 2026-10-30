<?php

namespace Jarryd\Sunset\Integrations;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jarryd\Sunset\Support\SunsetRoutes;

/**
 * Optional extras when jarryd/ghost-buster is installed. Sunset never requires
 * it: it only reads Ghost Buster's route_usage table.
 */
class GhostBuster
{
    public static function installed(): bool
    {
        return class_exists('Jarryd\GhostBuster\GhostBusterServiceProvider') && Schema::hasTable('route_usage');
    }

    /**
     * Ghost Buster's usage, keyed like sunset_calls: "METHOD name-or-uri".
     *
     * @return Collection<string, \stdClass>
     */
    public static function usage(): Collection
    {
        return DB::table('route_usage')
            ->get(['route_name', 'method', 'hits', 'last_used_at'])
            ->mapWithKeys(fn (object $row) => ["{$row->method} {$row->route_name}" => $row]);
    }

    /**
     * A route's usage across its methods, as of Ghost Buster's last sync.
     *
     * @param  Collection<string, \stdClass>  $usage
     * @return array{hits: int, last_used_at: CarbonImmutable|null}
     */
    public static function forRoute(Collection $usage, Route $route): array
    {
        $key = SunsetRoutes::key($route);
        $rows = collect($route->methods())->map(fn (string $method) => $usage->get("{$method} {$key}"))->filter();
        $lastUsed = $rows->max('last_used_at');

        return [
            'hits' => (int) $rows->sum('hits'),
            'last_used_at' => is_string($lastUsed) ? CarbonImmutable::parse($lastUsed) : null,
        ];
    }
}
