@props([
    'previous' => null,
    'next' => null,
])

{{-- Next is where a reader at the end of a page is heading, so it is the card.
     Previous is a link beside it. Either names its section when that differs
     from this page's. --}}
@if ($previous || $next)
    <nav data-vellum-pagination aria-label="Page" class="vellum-pagination">
        @if ($previous)
            <a
                href="{{ $previous['href'] }}"
                data-vellum-pagination-prev
                x-data="vellumPrefetchHover"
                x-on:pointerenter="onEnter()"
                class="vellum-pagination-prev"
            >
                <span class="vellum-pagination-label">Previous{{ ! empty($previous['section']) ? ' · '.$previous['section'] : '' }}</span>
                <span class="vellum-pagination-title">
                    {!! \Vellum\Support\Icons::caretLeft(['class' => 'h-4 w-4 shrink-0']) !!}
                    {{ $previous['title'] }}
                </span>
            </a>
        @endif

        @if ($next)
            <a
                href="{{ $next['href'] }}"
                data-vellum-pagination-next
                x-data="vellumPrefetchHover"
                x-on:pointerenter="onEnter()"
                class="vellum-pagination-next"
            >
                <span class="vellum-pagination-label">Next{{ ! empty($next['section']) ? ' · '.$next['section'] : '' }}</span>
                <span class="vellum-pagination-title">
                    {{ $next['title'] }}
                    {!! \Vellum\Support\Icons::caretRight(['class' => 'h-4 w-4 shrink-0']) !!}
                </span>
                @if (! empty($next['description']))
                    <span class="vellum-pagination-description">{{ $next['description'] }}</span>
                @endif
            </a>
        @endif
    </nav>
@endif
