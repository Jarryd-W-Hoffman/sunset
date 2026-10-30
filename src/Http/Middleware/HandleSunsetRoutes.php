<?php

namespace Jarryd\Sunset\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Jarryd\Sunset\Support\ReplacementUrl;
use Jarryd\Sunset\Support\SunsetReader;
use Symfony\Component\HttpFoundation\Response;

class HandleSunsetRoutes
{
    public function __construct(protected ReplacementUrl $replacements) {}

    /**
     * Add deprecation headers to responses from sunset routes.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $sunset = $route instanceof Route ? SunsetReader::forRoute($route) : null;

        $response = $next($request);

        if ($sunset === null) {
            return $response;
        }

        // Headers the app sets itself are left alone.
        $headers = $response->headers;

        if (! $headers->has('Sunset')) {
            $headers->set('Sunset', $sunset->date()->utc()->toRfc7231String());
        }

        // RFC 9745: a structured-field date, in seconds since the Unix epoch.
        if (($since = $sunset->since()) && ! $headers->has('Deprecation')) {
            $headers->set('Deprecation', '@'.$since->getTimestamp());
        }

        if ($url = $this->replacements->for($sunset, $request)) {
            $headers->set('Link', "<{$url}>; rel=\"successor-version\"", false);
        }

        return $response;
    }
}
