<?php

namespace Jarryd\Sunset;

use Carbon\CarbonInterface;

enum Stage: string
{
    case Deprecated = 'deprecated';
    case Retired = 'retired';
    case SafeToDelete = 'safe to delete';

    /**
     * A route is safe to delete once it's been retired for at least quiet_days,
     * with no calls in that time.
     */
    public static function for(Sunset $sunset, ?CarbonInterface $lastCalledAt): self
    {
        $now = now();

        if ($now->lt($sunset->date())) {
            return self::Deprecated;
        }

        $quietSince = $now->subDays((int) config('sunset.quiet_days', 30));

        if ($sunset->date()->lte($quietSince) && ($lastCalledAt === null || $lastCalledAt->lt($quietSince))) {
            return self::SafeToDelete;
        }

        return self::Retired;
    }

    public function label(): string
    {
        return match ($this) {
            self::Deprecated => 'Deprecated',
            self::Retired => 'Retired',
            self::SafeToDelete => 'Safe to delete',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Deprecated => 'yellow',
            self::Retired => 'magenta',
            self::SafeToDelete => 'green',
        };
    }
}
