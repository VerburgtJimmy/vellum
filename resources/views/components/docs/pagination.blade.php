@props([
    'previous' => null,
    'next' => null,
])

@php
    $both = $previous && $next;
@endphp

@if ($previous || $next)
    <nav data-vellum-pagination aria-label="Page" class="mt-16 grid grid-cols-1 gap-3 {{ $both ? 'sm:grid-cols-2' : '' }}">
        @if ($previous)
            <a
                href="{{ $previous['href'] }}"
                data-vellum-pagination-prev
                x-data="vellumPrefetchHover"
                x-on:pointerenter="onEnter()"
                class="group flex flex-col gap-2 rounded-lg border border-border p-4 text-sm transition-colors hover:bg-accent/50"
            >
                <span class="inline-flex items-center gap-1.5 font-semibold text-foreground">
                    <span class="sr-only">Previous:</span>
                    {!! \Vellum\Support\Icons::caretLeft() !!}
                    {{ $previous['title'] }}
                </span>
                @if (! empty($previous['description']))
                    <span class="truncate text-muted-foreground">{{ $previous['description'] }}</span>
                @endif
            </a>
        @endif

        @if ($next)
            <a
                href="{{ $next['href'] }}"
                data-vellum-pagination-next
                x-data="vellumPrefetchHover"
                x-on:pointerenter="onEnter()"
                class="group flex flex-col gap-2 rounded-lg border border-border p-4 text-end text-sm transition-colors hover:bg-accent/50"
            >
                <span class="inline-flex flex-row-reverse items-center gap-1.5 font-semibold text-foreground">
                    <span class="sr-only">Next:</span>
                    {!! \Vellum\Support\Icons::caretRight() !!}
                    {{ $next['title'] }}
                </span>
                @if (! empty($next['description']))
                    <span class="truncate text-muted-foreground">{{ $next['description'] }}</span>
                @endif
            </a>
        @endif
    </nav>
@endif
