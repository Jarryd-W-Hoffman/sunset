<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | What happens once a route's sunset date has passed. "warn" keeps serving
    | the route, with deprecation headers, and logs each call. "enforce"
    | redirects page requests that have a replacement, and returns 410 Gone
    | for everything else. A route's own mode wins over this setting.
    |
    */

    'mode' => env('SUNSET_MODE', 'warn'),

    /*
    |--------------------------------------------------------------------------
    | Grace Days
    |--------------------------------------------------------------------------
    |
    | Days after a route's sunset date before "enforce" mode kicks in.
    |
    */

    'grace_days' => 0,

    /*
    |--------------------------------------------------------------------------
    | Quiet Days
    |--------------------------------------------------------------------------
    |
    | Days without any calls before a retired route is reported as safe to
    | delete by sunset:list and sunset:check.
    |
    */

    'quiet_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | The middleware groups Sunset's middleware is added to. Routes using
    | ->sunset() get it automatically wherever they're defined.
    |
    */

    'middleware_groups' => [
        'web',
        'api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording Calls
    |--------------------------------------------------------------------------
    |
    | Record who calls each sunset route, for sunset:report. IP addresses are
    | personal data in most places, so they're only recorded if you opt in.
    |
    */

    'record_calls' => true,

    'record_ip' => false,

    /*
    |--------------------------------------------------------------------------
    | Notice View
    |--------------------------------------------------------------------------
    |
    | The Blade view used by <x-sunset::notice /> on sunset pages.
    |
    */

    'notice_view' => 'sunset::notice',

];
