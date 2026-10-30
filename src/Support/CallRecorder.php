<?php

namespace Jarryd\Sunset\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class CallRecorder
{
    /**
     * Upsert a call: one row per route, method and caller. first_called_at is
     * only set on insert; last_called_at, the IP and the hit count update.
     */
    public static function record(string $route, string $method, string $caller, ?string $ip, DateTimeInterface $calledAt): void
    {
        $query = DB::table('sunset_calls');
        $existingHits = $query->getGrammar()->wrap('sunset_calls.hits');

        $query->upsert([
            'route' => $route,
            'method' => $method,
            'caller' => $caller,
            'ip' => $ip,
            'hits' => 1,
            'first_called_at' => $calledAt,
            'last_called_at' => $calledAt,
        ], ['route', 'method', 'caller'], [
            'ip',
            'last_called_at',
            // The only variable part is the column name, quoted by the grammar: never user input.
            // @phpstan-ignore argument.type
            'hits' => DB::raw("{$existingHits} + 1"),
        ]);
    }
}
