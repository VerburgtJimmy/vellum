@props([
    'navigation' => [],
    'document' => null,
    'searchInSidebar' => false,
    'searchHash' => null,
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
    'showBrand' => false,
    'chrome' => true,
])

@php
    use Vellum\Support\Cn;

    $activeSlug = $document?->slug ?? null;
    $links = config('vellum.links', []);

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
    {{ $attributes->except('class')->merge(['class' => Cn::merge(
        'flex h-full w-[250px] shrink-0 flex-col border-r border-border',
        $attributes->get('class'),
    )]) }}
    @if ($chrome)
        x-on:mouseenter="showPeek()"
        x-on:mouseleave="scheduleHidePeek()"
    @endif
    x-init="$nextTick(() => $el.querySelector('[data-vellum-nav-active]')?.scrollIntoView({ block: 'nearest' }))"
>
    @if ($chrome)
    <div class="flex items-center gap-2 px-3 py-3">
        @if ($showBrand)
            <div class="min-w-0 flex-1">
                <x-vellum::docs.brand />
            </div>
        @else
            <div class="min-w-0 flex-1"></div>
        @endif
        @if ($searchInSidebar && config('vellum.versions.enabled'))
            <x-vellum::docs.version-switcher
                :versions="$versions"
                :current-version="$currentVersion"
                :version-hrefs="$versionHrefs"
            />
        @endif
        <button
            type="button"
            data-vellum-sidebar-collapse
            data-vellum-button
            class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:inline-flex"
            aria-label="Collapse sidebar"
            x-on:click="toggleSidebar()"
        >
            {!! \Vellum\Support\Icons::sidebar(['class' => 'h-4 w-4']) !!}
        </button>
    </div>
    @endif

    @if ($chrome && $searchInSidebar && config('vellum.search.enabled'))
        <div class="px-3 pb-3">
            <x-vellum::docs.search-trigger variant="sidebar" />
        </div>
    @endif

    <x-vellum::ui.scroll-area class="min-h-0 flex-1 px-4 pt-1 pb-4">
        <nav aria-label="Documentation" class="flex flex-col gap-1 text-sm">
            @include('vellum::components.docs.partials.nav-tree', [
                'nodes' => $navigation,
                'activeSlug' => $activeSlug,
                'containsActive' => $containsActive,
                'depth' => 0,
            ])
        </nav>
    </x-vellum::ui.scroll-area>

    @if ($chrome && $searchInSidebar)
        <div data-vellum-sidebar-footer class="flex items-center gap-1 border-t border-border px-3 py-2">
            <x-vellum::docs.theme-toggle placement="top" />

            @if (is_array($links))
                @foreach ($links as $link)
                    @php
                        $label = is_array($link) ? (string) ($link['label'] ?? '') : '';
                        $href = is_array($link) ? (string) ($link['href'] ?? '#') : '#';
                        $icon = is_array($link) ? (string) ($link['icon'] ?? '') : '';
                    @endphp
                    @if ($label !== '')
                        <a
                            href="{{ $href }}"
                            aria-label="{{ $label }}"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                            @if (str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif
                        >
                            @if ($icon === 'github')
                                {!! \Vellum\Support\Icons::github(['class' => 'h-4 w-4']) !!}
                            @else
                                {!! \Vellum\Support\Icons::arrowSquareOut(['class' => 'h-4 w-4']) !!}
                            @endif
                        </a>
                    @endif
                @endforeach
            @endif
        </div>
    @endif
</aside>
