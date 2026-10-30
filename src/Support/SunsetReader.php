<?php

namespace Jarryd\Sunset\Support;

use Closure;
use Illuminate\Routing\Route;
use Jarryd\Sunset\Sunset;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class SunsetReader
{
    /**
     * Attribute sunsets already read, keyed by "class::method". Null means none.
     *
     * @var array<string, Sunset|null>
     */
    protected static array $cache = [];

    /**
     * The sunset for a route: ->sunset() on the route wins, then a #[Sunset]
     * attribute on the controller method, then one on the controller class.
     */
    public static function forRoute(Route $route): ?Sunset
    {
        $sunset = $route->getAction('sunset');

        if (is_array($sunset)) {
            /** @var array{date: string, replacement?: string|null, since?: string|null, reason?: string|null, mode?: string|null} $sunset */
            return Sunset::fromArray($sunset);
        }

        return static::forAction($route->getAction('uses'));
    }

    /**
     * The #[Sunset] attribute for a controller action, if it has one.
     */
    public static function forAction(mixed $action): ?Sunset
    {
        if ($action instanceof Closure) {
            return null;
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action);
        } elseif (is_array($action) && count($action) === 2 && is_string($action[0]) && is_string($action[1])) {
            [$class, $method] = $action;
        } elseif (is_string($action) && class_exists($action)) {
            [$class, $method] = [$action, '__invoke'];
        } else {
            return null;
        }

        $key = "{$class}::{$method}";

        if (! array_key_exists($key, static::$cache)) {
            static::$cache[$key] = static::readAttribute($class, $method);
        }

        return static::$cache[$key];
    }

    /**
     * Forget all cached attributes.
     */
    public static function flushCache(): void
    {
        static::$cache = [];
    }

    protected static function readAttribute(string $class, string $method): ?Sunset
    {
        if (! class_exists($class)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
        } catch (ReflectionException) {
            return null;
        }

        // The class attribute comes from the routed controller, not a parent declaring the method.
        $attribute = $reflection->getAttributes(Sunset::class)[0]
            ?? (new ReflectionClass($class))->getAttributes(Sunset::class)[0]
            ?? null;

        return $attribute?->newInstance();
    }
}
