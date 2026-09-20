@props([
    'document' => null,
    'navigation' => [],
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
])

@php
    $links = config('vellum.links', []);
    $repo = config('vellum.repo');
    $searchEnabled = (bool) config('vellum.search.enabled');
@endphp

<header
    data-vellum-header
    class="sticky top-0 z-40 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:px-6"
>
    <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
        <x-vellum::docs.brand />

        @if (config('vellum.versions.enabled'))
            <x-vellum::docs.version-switcher
                :versions="$versions"
                :current-version="$currentVersion"
                :version-hrefs="$versionHrefs"
            />
        @endif
    </div>

    @if ($searchEnabled)
        <div
            data-vellum-header-search
            class="hidden min-w-0 flex-[1.6] justify-center md:flex"
        >
            <div class="w-full max-w-[28rem]">
                <x-vellum::docs.search-trigger variant="header" />
            </div>
        </div>
    @endif

    <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5">
        @if ($searchEnabled)
            <div class="md:hidden">
                <x-vellum::docs.search-trigger variant="icon" />
            </div>
        @endif

        @if (is_array($links) && $links !== [])
            <nav class="hidden items-center gap-1 lg:flex" aria-label="Header links">
                @foreach ($links as $link)
                    @php
                        $label = is_array($link) ? ($link['label'] ?? '') : '';
                        $href = is_array($link) ? ($link['href'] ?? '#') : '#';
                    @endphp
                    @if ($label !== '')
                        <a
                            href="{{ $href }}"
                            class="rounded-md px-2.5 py-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            @if (str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif
                        >{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        <x-vellum::docs.theme-toggle />

        @if (is_string($repo) && $repo !== '')
            <a
                href="{{ preg_replace('#/edit/.*$#', '', rtrim($repo, '/')) }}"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="GitHub repository"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            >
                {!! \Vellum\Support\Icons::github(['class' => 'h-4 w-4']) !!}
            </a>
        @endif

        <div class="md:hidden">
            <x-vellum::docs.mobile-sidebar :navigation="$navigation" :document="$document" />
        </div>
    </div>
</header>
