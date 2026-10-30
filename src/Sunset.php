<?php

namespace Jarryd\Sunset;

use Attribute;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Sunset
{
    public readonly ?Mode $mode;

    /**
     * @param  string  $date  The day the route is retired (Y-m-d), at midnight in the app's timezone.
     * @param  string|null  $replacement  A route name or URL to send callers to instead.
     * @param  string|null  $since  When the deprecation was announced (Y-m-d).
     * @param  string|null  $reason  A sentence for people, shown in notices and responses.
     * @param  Mode|string|null  $mode  Overrides the configured mode for this route.
     */
    public function __construct(
        public readonly string $date,
        public readonly ?string $replacement = null,
        public readonly ?string $since = null,
        public readonly ?string $reason = null,
        Mode|string|null $mode = null,
    ) {
        $sunsetsAt = self::parseDate($date, 'date');

        if ($since !== null && self::parseDate($since, 'since')->gt($sunsetsAt)) {
            throw new InvalidArgumentException("A sunset's since date [{$since}] can't be after its date [{$date}].");
        }

        $this->mode = $mode === null ? null : Mode::fromName($mode);
    }

    /**
     * The moment the route is retired: midnight at the start of its date, in the app's timezone.
     */
    public function date(): CarbonImmutable
    {
        return self::parseDate($this->date, 'date');
    }

    /**
     * The moment the deprecation was announced, if known.
     */
    public function since(): ?CarbonImmutable
    {
        return $this->since === null ? null : self::parseDate($this->since, 'since');
    }

    /**
     * Restore a sunset from the plain array stored in the route action.
     *
     * @param  array{date: string, replacement?: string|null, since?: string|null, reason?: string|null, mode?: string|null}  $sunset
     */
    public static function fromArray(array $sunset): self
    {
        return new self(
            $sunset['date'],
            $sunset['replacement'] ?? null,
            $sunset['since'] ?? null,
            $sunset['reason'] ?? null,
            $sunset['mode'] ?? null,
        );
    }

    /**
     * Sunsets are stored as plain arrays so they can be exported by route:cache.
     *
     * @return array{date: string, replacement: string|null, since: string|null, reason: string|null, mode: string|null}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'replacement' => $this->replacement,
            'since' => $this->since,
            'reason' => $this->reason,
            'mode' => $this->mode?->value,
        ];
    }

    /**
     * Parse a Y-m-d date at midnight in the default timezone, which Laravel
     * sets from app.timezone. Rejects other formats and impossible dates.
     */
    protected static function parseDate(string $value, string $field): CarbonImmutable
    {
        // Carbon throws on malformed input, and silently rolls impossible dates
        // (2027-02-30) over, so both cases end up with the same clear message.
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, date_default_timezone_get());
        } catch (InvalidFormatException) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Invalid sunset {$field} [{$value}]. Expected a real date in Y-m-d format, such as 2027-03-01.");
        }

        return $date;
    }
}
