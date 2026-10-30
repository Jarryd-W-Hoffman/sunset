<?php

namespace Jarryd\Sunset\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CallerIdentifier
{
    /**
     * A custom way to identify callers, set with Sunset::identifyCallersUsing().
     *
     * @var (Closure(Request): (string|null))|null
     */
    protected static ?Closure $using = null;

    /**
     * @param  (Closure(Request): (string|null))|null  $callback
     */
    public static function using(?Closure $callback): void
    {
        static::$using = $callback;
    }

    /**
     * Who made the request, as a short label such as "token:mobile-app".
     */
    public static function identify(Request $request): string
    {
        $caller = static::$using ? (static::$using)($request) : static::default($request);

        return Str::limit($caller === null || $caller === '' ? 'anonymous' : $caller, 255, '');
    }

    /**
     * An API token's name first, as it names the integration to contact, then
     * the signed-in user, then the client's user agent family.
     */
    protected static function default(Request $request): string
    {
        $user = $request->user();

        if (is_object($user) && method_exists($user, 'currentAccessToken')) {
            // Sanctum's personal access tokens have a name; its cookie-based TransientToken doesn't.
            $token = $user->currentAccessToken();
            $name = is_object($token) ? ($token->name ?? null) : null;

            if (is_string($name) && $name !== '') {
                return "token:{$name}";
            }
        }

        if ($user instanceof Authenticatable) {
            $id = $user->getAuthIdentifier();

            if (is_scalar($id)) {
                return 'user:'.$id;
            }
        }

        return static::agent((string) $request->userAgent());
    }

    /**
     * The user agent's family: "browser", "bot", or the client's own name, such as "okhttp".
     */
    protected static function agent(string $userAgent): string
    {
        if (trim($userAgent) === '') {
            return 'anonymous';
        }

        if (preg_match('/bot|crawl|spider|slurp/i', $userAgent)) {
            return 'agent:bot';
        }

        if (str_starts_with($userAgent, 'Mozilla/')) {
            return 'agent:browser';
        }

        // "okhttp/4.9.3" or "python-requests/2.31" → the name before the version.
        return 'agent:'.strtolower((string) preg_replace('/[\/\s].*$/', '', trim($userAgent)));
    }
}
