<?php

namespace Jarryd\Sunset\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sunset:report
        {route? : Only this route (its name, or its URI if it has none)}
        {--since= : Only callers seen in the last N days}
        {--json : Output the callers as JSON}';

    /**
     * @var string
     */
    protected $description = "Show who's still calling sunset routes, busiest first.";

    public function handle(): int
    {
        $since = $this->option('since');

        if ($since !== null && (! is_numeric($since) || (int) $since != $since || (int) $since < 1)) {
            $this->error('The --since option must be a whole number of days, 1 or more.');

            return self::INVALID;
        }

        $calls = DB::table('sunset_calls')
            ->when($this->argument('route'), fn ($query, $route) => $query->where('route', $route))
            ->when($since, fn ($query, $days) => $query->where('last_called_at', '>=', now()->subDays((int) $days)))
            ->orderByDesc('hits')
            ->orderBy('route')
            ->orderBy('caller')
            ->get();

        if ($this->option('json')) {
            $this->line((string) json_encode($calls->map(fn (object $call) => [
                'route' => $call->route,
                'method' => $call->method,
                'caller' => $call->caller,
                'ip' => $call->ip,
                'hits' => (int) $call->hits,
                'first_called_at' => CarbonImmutable::parse($call->first_called_at)->toIso8601String(),
                'last_called_at' => CarbonImmutable::parse($call->last_called_at)->toIso8601String(),
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($calls->isEmpty()) {
            $this->info('No calls to sunset routes have been recorded'.($since ? " in the last {$since} days." : '.'));

            return self::SUCCESS;
        }

        $showIp = $calls->contains(fn (object $call) => $call->ip !== null);

        $this->table(
            array_merge(['Route', 'Method', 'Caller'], $showIp ? ['IP'] : [], ['Hits', 'Last called', 'First called']),
            $calls->map(fn (object $call) => array_merge(
                [$call->route, $call->method, $call->caller],
                $showIp ? [$call->ip ?? '–'] : [],
                [
                    number_format((int) $call->hits),
                    CarbonImmutable::parse($call->last_called_at)->diffForHumans(),
                    CarbonImmutable::parse($call->first_called_at)->format('j M Y'),
                ],
            )),
        );

        return self::SUCCESS;
    }
}
