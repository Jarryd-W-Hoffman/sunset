<?php

namespace Jarryd\Sunset\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jarryd\Sunset\Events\DeprecatedRouteCalled;
use Jarryd\Sunset\Sunset;
use Jarryd\Sunset\Support\CallerIdentifier;
use Jarryd\Sunset\Support\CallRecorder;
use Jarryd\Sunset\Support\EffectiveMode;
use Jarryd\Sunset\Support\ReplacementUrl;
use Jarryd\Sunset\Support\SunsetReader;
use Jarryd\Sunset\Support\SunsetRoutes;
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

        if (EffectiveMode::isEnforced($sunset)) {
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
     * Record the call once the response has been sent, so callers never wait on it.
     */
    public function terminate(Request $request, Response $response): void
    {
        $route = $request->route();
        $sunset = $route instanceof Route ? SunsetReader::forRoute($route) : null;

        if ($sunset === null) {
            return;
        }

        $key = SunsetRoutes::key($route);
        $method = $request->method();
        $caller = CallerIdentifier::identify($request);
        $now = now();

        if (config('sunset.record_calls', true)) {
            CallRecorder::record($key, $method, $caller, config('sunset.record_ip', false) ? $request->ip() : null, $now);
        }

        // At most once per caller, route and method each day.
        $eventKey = 'sunset:called:'.sha1("{$key}|{$method}|{$caller}|{$now->toDateString()}");

        if (Cache::add($eventKey, true, $now->copy()->endOfDay())) {
            DeprecatedRouteCalled::dispatch($key, $method, $caller, $sunset, $now->gte($sunset->date()));
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
