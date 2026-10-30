<?php

namespace Jarryd\Sunset\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Jarryd\Sunset\Sunset;

class ReplacementUrl
{
    public function __construct(protected Router $router) {}

    /**
     * The URL of a sunset's replacement, or null if it has none or it can't be
     * built. A replacement is a route name, an absolute URL, or a path.
     */
    public function for(Sunset $sunset, Request $request): ?string
    {
        $replacement = $sunset->replacement;

        if ($replacement === null || $replacement === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $replacement)) {
            return $replacement;
        }

        if (str_starts_with($replacement, '/')) {
            return url($replacement);
        }

        $route = $this->router->getRoutes()->getByName($replacement);

        return $route ? $this->urlTo($route, $replacement, $request) : null;
    }

    /**
     * Reuse the current route's parameters where the replacement needs them,
     * so /blog/legacy/{post} can point at /blog/{post}.
     */
    protected function urlTo(Route $route, string $name, Request $request): ?string
    {
        $current = $request->route();
        $available = $current instanceof Route ? $current->parameters() : [];
        $parameters = [];

        foreach ($route->parameterNames() as $parameter) {
            if (array_key_exists($parameter, $available)) {
                $parameters[$parameter] = $available[$parameter];
            } elseif (! str_contains($route->uri(), "{{$parameter}?}")) {
                return null;
            }
        }

        return route($name, $parameters);
    }
}
