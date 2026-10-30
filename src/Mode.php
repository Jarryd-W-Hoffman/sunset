<?php

namespace Jarryd\Sunset;

use InvalidArgumentException;

enum Mode: string
{
    case Warn = 'warn';
    case Enforce = 'enforce';

    /**
     * Resolve a mode from its name, failing loudly on typos.
     */
    public static function fromName(self|string $mode): self
    {
        if ($mode instanceof self) {
            return $mode;
        }

        return self::tryFrom(strtolower($mode)) ?? throw new InvalidArgumentException(sprintf(
            'Invalid sunset mode [%s]. Expected one of: %s.',
            $mode,
            implode(', ', array_column(self::cases(), 'value')),
        ));
    }
}
