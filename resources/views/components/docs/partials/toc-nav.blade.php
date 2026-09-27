@php
    $tocStyle = config('vellum.layout.toc') === 'line' ? 'line' : 'window';
@endphp
<nav
    aria-label="Table of contents"
    class="vellum-toc-nav relative text-sm {{ $class ?? '' }}"
    data-vellum-toc-nav
    data-vellum-toc-style="{{ $tocStyle }}"
>
    @if ($tocStyle === 'line')
        <svg class="vellum-toc-rail" data-vellum-toc-track aria-hidden="true">
            <path data-vellum-toc-track-path fill="none" stroke-width="1"></path>
        </svg>
        <svg class="vellum-toc-rail vellum-toc-rail-active" data-vellum-toc-thumb aria-hidden="true">
            <path data-vellum-toc-thumb-path fill="none" stroke-width="1"></path>
        </svg>
    @else
        <span class="vellum-toc-window" data-vellum-toc-window aria-hidden="true"></span>
        <span class="vellum-toc-hover" data-vellum-toc-hover aria-hidden="true"></span>
    @endif
    @include('vellum::components.docs.partials.toc-items', ['nodes' => $nodes, 'depth' => 0])
</nav>
