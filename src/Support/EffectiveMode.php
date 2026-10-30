<?php

namespace Jarryd\Sunset\Support;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Jarryd\Sunset\Mode;
use Jarryd\Sunset\Sunset;

class EffectiveMode
{
    /**
     * The route's own mode, else the configured one. A configured typo falls
     * back to warn, so a bad env value can't switch every sunset route off.
     */
    public static function for(Sunset $sunset): Mode
    {
        if ($sunset->mode !== null) {
            return $sunset->mode;
        }

        $configured = config('sunset.mode', 'warn');

        try {
            return Mode::fromName(is_string($configured) ? $configured : '');
        } catch (InvalidArgumentException $exception) {
            Log::error($exception->getMessage().' Falling back to warn.');

            return Mode::Warn;
        }
    }

    /**
     * Whether the route is switched off now: in enforce mode, and past its
     * sunset date plus the grace period.
     */
    public static function isEnforced(Sunset $sunset): bool
    {
        return static::for($sunset) === Mode::Enforce
            && now()->gte($sunset->date()->addDays((int) config('sunset.grace_days', 0)));
    }
}
