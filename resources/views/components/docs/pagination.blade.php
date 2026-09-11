@props([
    'previous' => null,
    'next' => null,
])

@if ($previous || $next)
    <nav data-vellum-pagination aria-label="Page" class="mt-12 grid gap-3 border-t border-border pt-8 sm:grid-cols-2">
        @if ($previous)
            <a
                href="{{ $previous['href'] }}"
                data-vellum-pagination-prev
                x-data="vellumPrefetchHover"
                x-on:pointerenter="onEnter()"
                class="group flex flex-col gap-1 rounded-lg border border-border p-4 transition-colors hover:bg-accent/50"
            >
                <span class="text-xs text-muted-foreground">Previous</span>
                <span class="font-medium text-foreground group-hover:underline">{{ $previous['title'] }}</span>
            </a>
        @else
            <div class="hidden sm:block"></div>
        @endif

        @if ($next)
            <a
                href="{{ $next['href'] }}"
                data-vellum-pagination-next
                x-data="vellumPrefetchHover"
                x-on:pointerenter="onEnter()"
                class="group flex flex-col gap-1 rounded-lg border border-border p-4 text-right transition-colors hover:bg-accent/50 sm:items-end"
            >
                <span class="text-xs text-muted-foreground">Next</span>
                <span class="font-medium text-foreground group-hover:underline">{{ $next['title'] }}</span>
            </a>
        @endif
    </nav>
@endif
