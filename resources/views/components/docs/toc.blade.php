@props([
    'toc' => [],
    'document' => null,
    'placement' => 'both',
])

@php
    $full = (bool) ($document?->full ?? false);
    $flatIds = [];

    $collectIds = static function (array $nodes) use (&$collectIds, &$flatIds): void {
        foreach ($nodes as $node) {
            if (isset($node['id']) && is_string($node['id'])) {
                $flatIds[] = $node['id'];
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $collectIds($node['children']);
            }
        }
    };

    $collectIds($toc);
    $showDesktop = in_array($placement, ['both', 'desktop'], true);
    $showMobile = in_array($placement, ['both', 'mobile'], true);
@endphp

@if (! $full && $toc !== [])
    @if ($showDesktop)
        <aside
            data-vellum-toc
            class="hidden w-[220px] shrink-0 lg:block"
            x-data="vellumScrollSpy(@js($flatIds))"
        >
            <div class="sticky top-14 max-h-[calc(100vh-3.5rem)] overflow-y-auto py-6 pl-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">On this page</p>
                <nav aria-label="Table of contents" class="flex flex-col gap-1 border-l border-border text-sm">
                    @include('vellum::components.docs.partials.toc-items', ['nodes' => $toc, 'depth' => 0])
                </nav>
            </div>
        </aside>
    @endif

    @if ($showMobile)
        <div data-vellum-toc-mobile class="mb-6 lg:hidden" x-data="vellumScrollSpy(@js($flatIds))">
            <x-vellum::ui.collapsible>
                <x-slot:trigger>
                    <div class="flex cursor-pointer items-center justify-between rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-accent/50">
                        <span>On this page</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-muted-foreground transition-transform" :class="open && 'rotate-180'" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </div>
                </x-slot:trigger>
                <x-slot:content>
                    <nav aria-label="Table of contents" class="mt-2 flex flex-col gap-1 border-l border-border py-1 text-sm">
                        @include('vellum::components.docs.partials.toc-items', ['nodes' => $toc, 'depth' => 0])
                    </nav>
                </x-slot:content>
            </x-vellum::ui.collapsible>
        </div>
    @endif
@endif
