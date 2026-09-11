@php
    /** @var list<array<string, mixed>> $nodes */
    /** @var (callable(array<string, mixed>): bool)|null $containsActive */
    $containsActive = $containsActive ?? static fn (array $node): bool => false;
    $activeSlug = $activeSlug ?? null;
    $depth = $depth ?? 0;
@endphp

@foreach ($nodes as $node)
    @php
        $type = $node['type'] ?? null;
    @endphp

    @if ($type === 'separator')
        <div class="mt-4 mb-1 px-2 text-xs font-medium uppercase tracking-wide text-muted-foreground" role="presentation">
            {{ $node['title'] ?? '' }}
        </div>
    @elseif ($type === 'page')
        @php
            $isActive = $activeSlug !== null && ($node['slug'] ?? null) === $activeSlug;
            $href = $node['href'] ?? '#';
        @endphp
        <a
            href="{{ $href }}"
            data-vellum-nav-link
            @if ($isActive) data-vellum-nav-active @endif
            x-data="vellumPrefetchHover"
            x-on:pointerenter="onEnter()"
            class="block rounded-md px-2 py-1.5 transition-colors {{ $isActive ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground hover:bg-accent/60 hover:text-accent-foreground' }}"
            @if ($isActive) aria-current="page" @endif
        >{{ $node['title'] ?? 'Untitled' }}</a>
    @elseif ($type === 'folder')
        @php
            $open = (bool) ($node['defaultOpen'] ?? false) || $containsActive($node);
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        @endphp
        <x-vellum::ui.collapsible :open="$open" class="flex flex-col gap-0.5">
            <x-slot:trigger>
                <div class="flex cursor-pointer items-center gap-1 rounded-md px-2 py-1.5 font-medium text-foreground hover:bg-accent/60">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform" :class="open && 'rotate-90'" aria-hidden="true">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                    <span class="truncate">{{ $node['title'] ?? 'Folder' }}</span>
                </div>
            </x-slot:trigger>
            <x-slot:content>
                <div class="ml-2 flex flex-col gap-0.5 border-l border-border pl-2">
                    @include('vellum::components.docs.partials.nav-tree', [
                        'nodes' => $children,
                        'activeSlug' => $activeSlug,
                        'containsActive' => $containsActive,
                        'depth' => $depth + 1,
                    ])
                </div>
            </x-slot:content>
        </x-vellum::ui.collapsible>
    @endif
@endforeach
