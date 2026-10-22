<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title')</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #faf7f2; color: #2a2a2a; }
        main { max-width: 32rem; margin: 12vh auto; padding: 0 1.5rem; }
        h1 { font-size: 1.5rem; line-height: 1.25; margin: 0 0 1rem; }
        p { line-height: 1.55; margin: 0 0 1.25rem; }
        .btn { display: inline-block; padding: .75rem 1.25rem; border: 0; border-radius: 6px; background: #2a2a2a; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .link { display: inline-block; margin-left: 1rem; color: #2a2a2a; }
    </style>
</head>
<body>
<main>
    @yield('content')
</main>
</body>
</html>
