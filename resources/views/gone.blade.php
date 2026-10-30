<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $message }}</title>
    <style>
        :root { color-scheme: light dark; --text: #1f2328; --muted: #59636e; --link: #0969da; --bg: #ffffff; }
        @media (prefers-color-scheme: dark) { :root { --text: #f0f6fc; --muted: #9198a1; --link: #4493f8; --bg: #0d1117; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 34rem; padding: 2rem 1.5rem; }
        h1 { font-size: 1.5rem; line-height: 1.3; margin: 0 0 .75rem; }
        p { margin: 0 0 1rem; color: var(--muted); }
        a { color: var(--link); font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $message }}</h1>

        @if ($reason)
            <p>{{ $reason }}</p>
        @endif

        @if ($replacement)
            <p><a href="{{ $replacement }}">Go to its replacement</a></p>
        @endif
    </main>
</body>
</html>
