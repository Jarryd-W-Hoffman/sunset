<?php

namespace Jarryd\Sunset\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Jarryd\Sunset\Integrations\GhostBuster;
use Jarryd\Sunset\Integrations\RouteNotes;
use Jarryd\Sunset\Support\EffectiveMode;
use Jarryd\Sunset\Support\SunsetReader;
use Jarryd\Sunset\Support\SunsetRoutes;

class ListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sunset:list
        {--candidates : List routes with a deprecated note but no sunset date (needs Route Notes)}
        {--json : Output the routes as JSON}';

    /**
     * @var string
     */
    protected $description = 'List every route with a sunset date, its stage and how many callers it still has.';

    public function handle(SunsetRoutes $routes, Router $router): int
    {
        if ($this->option('candidates')) {
            return $this->candidates($router);
        }

        $entries = $routes->all();
        $usage = GhostBuster::installed() ? GhostBuster::usage() : null;

        if ($this->option('json')) {
            $this->line((string) json_encode($entries->map(function (array $entry) use ($usage) {
                $row = [
                    'route' => $entry['key'],
                    'methods' => $entry['route']->methods(),
                    'sunset' => $entry['sunset']->date,
                    'since' => $entry['sunset']->since,
                    'stage' => $entry['stage']->value,
                    'mode' => EffectiveMode::for($entry['sunset'])->value,
                    'replacement' => $entry['sunset']->replacement,
                    'reason' => $entry['sunset']->reason,
                    'callers' => $entry['callers'],
                    'last_called_at' => $entry['last_called_at']?->toIso8601String(),
                ];

                if ($usage !== null) {
                    $ghost = GhostBuster::forRoute($usage, $entry['route']);
                    $row['ghost_buster'] = ['hits' => $ghost['hits'], 'last_used_at' => $ghost['last_used_at']?->toIso8601String()];
                }

                return $row;
            }), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($entries->isEmpty()) {
            $this->info('No routes have a sunset date.');

            return self::SUCCESS;
        }

        $headers = ['Route', 'Methods', 'Sunset', 'When', 'Stage', 'Mode', 'Replacement', 'Callers'];

        if ($usage !== null) {
            array_push($headers, 'Last used', 'Hits');
        }

        $this->table($headers, $entries->map(function (array $entry) use ($usage) {
            $row = [
                $entry['key'],
                implode('|', $entry['route']->methods()),
                $entry['sunset']->date()->format('j M Y'),
                $entry['sunset']->date()->diffForHumans(),
                "<fg={$entry['stage']->color()}>{$entry['stage']->label()}</>",
                EffectiveMode::for($entry['sunset'])->value,
                $entry['sunset']->replacement ?? '–',
                $entry['callers'],
            ];

            if ($usage !== null) {
                $ghost = GhostBuster::forRoute($usage, $entry['route']);
                array_push($row, $ghost['last_used_at']?->diffForHumans() ?? 'Never', number_format($ghost['hits']));
            }

            return $row;
        }));

        $this->line('  <fg=gray>Callers: distinct callers in the last '.config('sunset.quiet_days', 30).' days. See sunset:report for who they are.</>');

        if ($usage !== null) {
            $this->line('  <fg=gray>Last used and Hits: from Ghost Buster, as of its last sync.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Routes marked deprecated with Route Notes that don't have a sunset date yet.
     */
    protected function candidates(Router $router): int
    {
        if (! RouteNotes::installed()) {
            $this->error('The --candidates option needs jarryd/route-notes: composer require jarryd/route-notes');

            return self::INVALID;
        }

        $candidates = collect($router->getRoutes()->getRoutes())
            ->reject(fn (Route $route) => SunsetReader::forRoute($route) !== null)
            ->map(fn (Route $route) => [
                'route' => SunsetRoutes::key($route),
                'methods' => $route->methods(),
                'notes' => RouteNotes::deprecatedNotes($route),
            ])
            ->filter(fn (array $candidate) => $candidate['notes'] !== [])
            ->values();

        if ($this->option('json')) {
            $this->line((string) json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($candidates->isEmpty()) {
            $this->info('No routes are marked deprecated without a sunset date.');

            return self::SUCCESS;
        }

        $this->table(['Route', 'Methods', 'Deprecated note'], $candidates->map(fn (array $candidate) => [
            $candidate['route'],
            implode('|', $candidate['methods']),
            implode("\n", $candidate['notes']),
        ]));

        $this->line('  <fg=gray>Give each one a date with ->sunset(\'YYYY-MM-DD\') or #[Sunset].</>');

        return self::SUCCESS;
    }
}
