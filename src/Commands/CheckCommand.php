<?php

namespace Jarryd\Sunset\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Jarryd\Sunset\Stage;
use Jarryd\Sunset\Support\SunsetRoutes;

class CheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sunset:check';

    /**
     * @var string
     */
    protected $description = "For CI: fail on a replacement that doesn't exist, or a retired route that's safe to delete.";

    public function handle(SunsetRoutes $routes, Router $router): int
    {
        $entries = $routes->all();
        $problems = [];

        foreach ($entries as $entry) {
            $replacement = $entry['sunset']->replacement;

            if ($replacement !== null && ! $this->isUrlOrPath($replacement) && ! $router->has($replacement)) {
                $problems[] = "{$entry['key']}: its replacement [{$replacement}] isn't a route name, URL or path.";
            }

            if ($entry['stage'] === Stage::SafeToDelete) {
                $problems[] = "{$entry['key']}: retired on {$entry['sunset']->date()->format('j M Y')} and quiet for "
                    .config('sunset.quiet_days', 30).' days. It\'s safe to delete.';
            }
        }

        if ($problems === []) {
            $this->info('All '.$entries->count().' sunset '.Str::plural('route', $entries->count()).' look good.');

            return self::SUCCESS;
        }

        $this->error('Found '.count($problems).' '.Str::plural('problem', count($problems)).':');

        foreach ($problems as $problem) {
            $this->line("  - {$problem}");
        }

        return self::FAILURE;
    }

    protected function isUrlOrPath(string $replacement): bool
    {
        return (bool) preg_match('#^(https?://|/)#i', $replacement);
    }
}
