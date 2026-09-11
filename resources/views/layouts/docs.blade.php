<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('vellum.name'))</title>
    @if (! empty($description ?? null))
        <meta name="description" content="{{ $description }}">
    @endif
</head>
<body>
    <a href="#vellum-content">Skip to content</a>
    <main id="vellum-content">
        @yield('content')
    </main>
</body>
</html>
