<?php

namespace Jarryd\Sunset;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Jarryd\Sunset\Support\ReplacementUrl;
use Jarryd\Sunset\Support\SunsetReader;
use JsonSerializable;

/**
 * What a page should tell people about its sunset, from Sunset::current().
 */
final class SunsetNotice implements JsonSerializable
{
    public function __construct(
        public readonly Sunset $sunset,
        public readonly string $message,
        public readonly ?string $replacement,
        public readonly bool $pastSunset,
    ) {}

    /**
     * The notice for a request's route, or null if it has no sunset.
     */
    public static function forRequest(Request $request): ?self
    {
        $route = $request->route();
        $sunset = $route instanceof Route ? SunsetReader::forRoute($route) : null;

        if ($sunset === null) {
            return null;
        }

        $pastSunset = now()->gte($sunset->date());
        $date = $sunset->date()->format('j F Y');

        return new self(
            $sunset,
            $pastSunset ? "This page was retired on {$date}." : "This page is being retired on {$date}.",
            app(ReplacementUrl::class)->for($sunset, $request),
            $pastSunset,
        );
    }

    public function date(): CarbonImmutable
    {
        return $this->sunset->date();
    }

    public function reason(): ?string
    {
        return $this->sunset->reason;
    }

    /**
     * Plain data for Inertia props, Livewire or JSON.
     *
     * @return array{sunset: string, message: string, reason: string|null, replacement: string|null, past_sunset: bool}
     */
    public function toArray(): array
    {
        return [
            'sunset' => $this->sunset->date,
            'message' => $this->message,
            'reason' => $this->sunset->reason,
            'replacement' => $this->replacement,
            'past_sunset' => $this->pastSunset,
        ];
    }

    /**
     * @return array{sunset: string, message: string, reason: string|null, replacement: string|null, past_sunset: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
