<?php

namespace Jarryd\Sunset\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Jarryd\Sunset\Mode;
use Jarryd\Sunset\Sunset;
use Jarryd\Sunset\Support\ReplacementUrl;
use Jarryd\Sunset\Support\SunsetReader;
use Symfony\Component\HttpFoundation\Response;

class HandleSunsetRoutes
{
    public function __construct(protected ReplacementUrl $replacements) {}

    /**
     * Serve, redirect or retire a sunset route, then add deprecation headers.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $sunset = $route instanceof Route ? SunsetReader::forRoute($route) : null;

        if ($sunset === null) {
            return $next($request);
        }

        $replacement = $this->replacements->for($sunset, $request);

        if ($this->isEnforced($sunset)) {
            $response = $this->retire($sunset, $request, $replacement);
        } else {
            if (now()->gte($sunset->date())) {
                Log::warning('A sunset route was called after its sunset date.', [
                    'route' => $route->getName() ?? $route->uri(),
                    'method' => $request->method(),
                    'sunset' => $sunset->date,
                ]);
            }

            $response = $next($request);
        }

        return $this->addHeaders($response, $sunset, $replacement);
    }

    /**
     * Whether the route should be switched off now: in enforce mode, and past
     * its sunset date plus the grace period.
     */
    protected function isEnforced(Sunset $sunset): bool
    {
        return $this->mode($sunset) === Mode::Enforce
            && now()->gte($sunset->date()->addDays((int) config('sunset.grace_days', 0)));
    }

    /**
     * The route's own mode, else the configured one. A configured typo falls
     * back to warn, so a bad env value can't switch every sunset route off.
     */
    protected function mode(Sunset $sunset): Mode
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
     * Redirect page requests to the replacement; everything else is gone.
     * Non-GET requests are never redirected, as replaying them elsewhere isn't safe.
     */
    protected function retire(Sunset $sunset, Request $request, ?string $replacement): Response
    {
        if ($replacement !== null && in_array($request->method(), ['GET', 'HEAD'], true) && ! $request->expectsJson()) {
            // The raw query keeps the caller's parameter order; getQueryString() sorts it.
            $query = parse_url($request->getRequestUri(), PHP_URL_QUERY);

            return new RedirectResponse(
                ! is_string($query) || $query === '' || str_contains($replacement, '?') ? $replacement : "{$replacement}?{$query}",
                301,
            );
        }

        $message = 'This '.($request->expectsJson() ? 'endpoint' : 'page').' was retired on '.$sunset->date()->format('j F Y').'.';

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $message,
                'reason' => $sunset->reason,
                'replacement' => $replacement,
                'sunset' => $sunset->date,
            ], 410);
        }

        return response()->view('sunset::gone', [
            'message' => $message,
            'reason' => $sunset->reason,
            'replacement' => $replacement,
        ], 410);
    }

    /**
     * Headers the app sets itself are left alone.
     */
    protected function addHeaders(Response $response, Sunset $sunset, ?string $replacement): Response
    {
        $headers = $response->headers;

        if (! $headers->has('Sunset')) {
            $headers->set('Sunset', $sunset->date()->utc()->toRfc7231String());
        }

        // RFC 9745: a structured-field date, in seconds since the Unix epoch.
        if (($since = $sunset->since()) && ! $headers->has('Deprecation')) {
            $headers->set('Deprecation', '@'.$since->getTimestamp());
        }

        if ($replacement !== null) {
            $headers->set('Link', "<{$replacement}>; rel=\"successor-version\"", false);
        }

        return $response;
    }
}
