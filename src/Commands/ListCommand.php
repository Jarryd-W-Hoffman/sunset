<?php

namespace Jarryd\Sunset\Commands;

use Illuminate\Console\Command;
use Jarryd\Sunset\Support\EffectiveMode;
use Jarryd\Sunset\Support\SunsetRoutes;

class ListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sunset:list
        {--json : Output the routes as JSON}';

    /**
     * @var string
     */
    protected $description = 'List every route with a sunset date, its stage and how many callers it still has.';

    public function handle(SunsetRoutes $routes): int
    {
        $entries = $routes->all();

        if ($this->option('json')) {
            $this->line((string) json_encode($entries->map(fn (array $entry) => [
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
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($entries->isEmpty()) {
            $this->info('No routes have a sunset date.');

            return self::SUCCESS;
        }

        $this->table(
            ['Route', 'Methods', 'Sunset', 'When', 'Stage', 'Mode', 'Replacement', 'Callers'],
            $entries->map(fn (array $entry) => [
                $entry['key'],
                implode('|', $entry['route']->methods()),
                $entry['sunset']->date()->format('j M Y'),
                $entry['sunset']->date()->diffForHumans(),
                "<fg={$entry['stage']->color()}>{$entry['stage']->label()}</>",
                EffectiveMode::for($entry['sunset'])->value,
                $entry['sunset']->replacement ?? '–',
                $entry['callers'],
            ]),
        );

        $this->line('  <fg=gray>Callers: distinct callers in the last '.config('sunset.quiet_days', 30).' days. See sunset:report for who they are.</>');

        return self::SUCCESS;
    }
}
