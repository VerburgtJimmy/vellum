@props([
    'navigation' => [],
    'document' => null,
])

@php
    $activeSlug = $document?->slug ?? null;

    $containsActive = static function (array $node) use (&$containsActive, $activeSlug): bool {
        if ($activeSlug === null) {
            return false;
        }

        if (($node['type'] ?? null) === 'page') {
            return ($node['slug'] ?? null) === $activeSlug;
        }

        if (($node['type'] ?? null) === 'folder' && isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child) && $containsActive($child)) {
                    return true;
                }
            }
        }

        return false;
    };
@endphp

<aside
    data-vellum-sidebar
    class="flex h-full w-[250px] shrink-0 flex-col border-r border-border bg-background"
    x-data
    x-init="$nextTick(() => $el.querySelector('[data-vellum-nav-active]')?.scrollIntoView({ block: 'nearest' }))"
>
    <x-vellum::ui.scroll-area class="flex-1 px-3 py-4">
        <nav aria-label="Documentation" class="flex flex-col gap-1 text-sm">
            @include('vellum::components.docs.partials.nav-tree', [
                'nodes' => $navigation,
                'activeSlug' => $activeSlug,
                'containsActive' => $containsActive,
                'depth' => 0,
            ])
        </nav>
    </x-vellum::ui.scroll-area>
</aside>
