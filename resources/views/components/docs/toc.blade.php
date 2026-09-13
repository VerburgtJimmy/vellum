@props([
    'toc' => [],
    'document' => null,
    'placement' => 'both',
    'stickyClass' => 'top-14 max-h-[calc(100vh-3.5rem)]',
    'mobileStickyClass' => 'top-14 md:top-0',
])

@php
    $full = (bool) ($document?->full ?? false);
    $flat = [];

    $collect = static function (array $nodes) use (&$collect, &$flat): void {
        foreach ($nodes as $node) {
            if (isset($node['id']) && is_string($node['id'])) {
                $flat[] = [
                    'id' => $node['id'],
                    'text' => is_string($node['text'] ?? null) ? $node['text'] : $node['id'],
                ];
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $collect($node['children']);
            }
        }
    };

    $collect($toc);
    $showDesktop = in_array($placement, ['both', 'desktop'], true);
    $showMobile = in_array($placement, ['both', 'mobile'], true);
    $pageTitle = is_string($document?->title ?? null) && $document->title !== ''
        ? $document->title
        : 'On this page';
@endphp

@if (! $full)
    @if ($showDesktop && $toc !== [])
        <aside
            data-vellum-toc
            class="hidden w-[220px] shrink-0 lg:block"
            x-data="vellumScrollSpy(@js($flat))"
        >
            <div class="sticky {{ $stickyClass }} overflow-y-auto py-6 pl-2">
                <p class="mb-3 flex items-center gap-2 text-[13px] font-medium text-muted-foreground">
                    {!! \Vellum\Support\Icons::listBullets(['class' => 'h-3.5 w-3.5']) !!}
                    <span>On this page</span>
                </p>
                @include('vellum::components.docs.partials.toc-nav', ['nodes' => $toc])
            </div>
        </aside>
    @elseif ($showDesktop)
        <div data-vellum-toc-placeholder class="hidden w-[220px] shrink-0 lg:block" aria-hidden="true"></div>
    @endif

    @if ($showMobile && $toc !== [])
        <div
            data-vellum-toc-mobile
            class="sticky {{ $mobileStickyClass }} relative z-30 lg:hidden"
            :class="open && 'z-[60]'"
            x-data="vellumScrollSpy(@js($flat), @js($pageTitle))"
            x-on:keydown.escape.window="if (open) open = false"
            x-on:click.outside="open = false"
        >
            <div
                class="border-b border-border bg-background/95 backdrop-blur"
                :class="open && 'shadow-lg'"
            >
            <button
                type="button"
                data-vellum-toc-popover-trigger
                class="flex h-10 w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm focus-visible:outline-none md:px-6"
                :aria-expanded="open.toString()"
                aria-controls="vellum-toc-popover-panel"
                x-on:click.stop="toggleOpen()"
            >
                <svg
                    role="progressbar"
                    viewBox="0 0 18 18"
                    class="h-[18px] w-[18px] shrink-0 text-muted-foreground"
                    :aria-valuenow="progress.toFixed(2)"
                    aria-valuemin="0"
                    aria-valuemax="1"
                    aria-label="Reading progress"
                >
                    <circle cx="9" cy="9" r="7.5" fill="none" stroke-width="1.5" class="vellum-toc-progress-track"></circle>
                    <circle
                        cx="9"
                        cy="9"
                        r="7.5"
                        fill="none"
                        stroke-width="1.5"
                        stroke="currentColor"
                        stroke-linecap="round"
                        transform="rotate(-90 9 9)"
                        stroke-dasharray="47.124"
                        :stroke-dashoffset="(47.124 * (1 - progress)).toFixed(3)"
                        class="vellum-toc-progress-thumb"
                    ></circle>
                </svg>
                <span
                    class="min-w-0 flex-1 truncate"
                    :class="open ? 'font-semibold text-foreground' : 'text-muted-foreground'"
                    x-text="open ? pageTitle : activeTitle"
                >{{ $pageTitle }}</span>
                {!! \Vellum\Support\Icons::caretDown(['class' => 'h-4 w-4 shrink-0 text-muted-foreground transition-transform', ':class' => "open && 'rotate-180'"]) !!}
            </button>
            <div
                id="vellum-toc-popover-panel"
                data-vellum-toc-popover-panel
                x-show="open"
                x-cloak
            >
                <div class="vellum-toc-popover-scroll max-h-[50vh] overflow-y-auto px-4 py-3 md:px-6">
                    @include('vellum::components.docs.partials.toc-nav', ['nodes' => $toc])
                </div>
            </div>
            </div>
        </div>
    @endif
@endif
