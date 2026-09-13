@php
    use Vellum\Support\Assets;

    $themeDefault = config('vellum.theme.default', 'system');
    $themeRadius = config('vellum.theme.radius', '0.5rem');
    $themePrimary = config('vellum.theme.primary');
    $rootStyles = ['--radius: '.$themeRadius];

    if ($themePrimary !== null && $themePrimary !== '') {
        $rootStyles[] = '--vellum-primary-hue: '.$themePrimary;
    }
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-vellum-search="{{ Assets::searchJsUrl() }}"
    data-vellum-anchor="{{ Assets::anchorJsUrl() }}"
    data-vellum-focus="{{ Assets::focusJsUrl() }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            try {
                var key = 'vellum-theme';
                var stored = localStorage.getItem(key);
                var preference = stored || @json($themeDefault);
                var dark = preference === 'dark' || (
                    preference === 'system'
                    && window.matchMedia('(prefers-color-scheme: dark)').matches
                );
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
            try {
                if (localStorage.getItem('vellum-sidebar') === 'collapsed') {
                    document.documentElement.setAttribute('data-vellum-sidebar', 'collapsed');
                }
            } catch (e) {}
        })();
    </script>
    <title>@yield('title', config('vellum.name'))</title>
    <link rel="icon" href="data:,">
    @if (! empty($description ?? null))
        <meta name="description" content="{{ $description }}">
    @endif
    @if (! empty(config('vellum.fonts')))
        {!! config('vellum.fonts') !!}
    @endif
    <style>:root { {{ implode('; ', $rootStyles) }} }</style>
    <link rel="stylesheet" href="{{ Assets::cssUrl() }}">
    <script type="module" src="{{ Assets::jsUrl() }}"></script>
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <a href="#vellum-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:shadow">Skip to content</a>
    @yield('content')
</body>
</html>
