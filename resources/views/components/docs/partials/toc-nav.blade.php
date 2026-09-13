<nav
    aria-label="Table of contents"
    class="vellum-toc-nav relative text-sm {{ $class ?? '' }}"
    data-vellum-toc-nav
>
    <svg class="vellum-toc-rail" data-vellum-toc-track aria-hidden="true">
        <path data-vellum-toc-track-path fill="none" stroke-width="1"></path>
    </svg>
    <svg class="vellum-toc-rail vellum-toc-rail-active" data-vellum-toc-thumb aria-hidden="true">
        <path data-vellum-toc-thumb-path fill="none" stroke-width="1"></path>
    </svg>
    <span class="vellum-toc-thumb-dot" data-vellum-toc-dot aria-hidden="true"></span>
    @include('vellum::components.docs.partials.toc-items', ['nodes' => $nodes, 'depth' => 0])
</nav>
