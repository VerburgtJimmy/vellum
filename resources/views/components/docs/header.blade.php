@props([
    'document' => null,
    'navigation' => [],
    'searchHash' => null,
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
])

@php
    $name = config('vellum.name', 'Docs');
    $logo = config('vellum.logo');
    $links = config('vellum.links', []);
    $repo = config('vellum.repo');
    $homeHref = route('vellum.docs.index');
@endphp

<header
    data-vellum-header
    class="sticky top-0 z-40 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:px-6"
>
    <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
        <div class="md:hidden">
            <x-vellum::docs.mobile-sidebar :navigation="$navigation" :document="$document" />
        </div>

        <a href="{{ $homeHref }}" class="flex min-w-0 items-center gap-2 font-semibold tracking-tight text-foreground hover:opacity-90">
            @if (is_string($logo) && $logo !== '')
                @if (str_ends_with($logo, '.svg') && is_file($logo))
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center [&>svg]:h-6 [&>svg]:w-6" aria-hidden="true">
                        {!! file_get_contents($logo) !!}
                    </span>
                @elseif (view()->exists($logo))
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center" aria-hidden="true">
                        @include($logo)
                    </span>
                @endif
            @endif
            <span class="truncate">{{ $name }}</span>
        </a>

        @if (config('vellum.versions.enabled'))
            <x-vellum::docs.version-switcher
                :versions="$versions"
                :current-version="$currentVersion"
                :version-hrefs="$versionHrefs"
            />
        @endif

        @if (is_array($links) && $links !== [])
            <nav class="ml-2 hidden items-center gap-1 lg:flex" aria-label="Header links">
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
    </div>

    <div class="flex shrink-0 items-center gap-1.5">
        @if (config('vellum.search.enabled'))
            <x-vellum::docs.search :search-hash="$searchHash" />
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                    <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                </svg>
            </a>
        @endif
    </div>
</header>
