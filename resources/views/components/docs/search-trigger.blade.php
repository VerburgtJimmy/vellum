@props([
    'variant' => 'header',
])

@php
    $hotkey = strtoupper((string) config('vellum.search.hotkey', 'k'));
    $base = 'inline-flex items-center gap-2 rounded-md text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
    $classes = match ($variant) {
        'sidebar' => $base.' h-9 w-full border border-input bg-background px-2.5',
        'icon' => $base.' h-8 w-8 justify-center rounded-lg',
        default => $base.' h-9 w-full justify-between rounded-full border border-input bg-muted/70 px-3',
    };
@endphp

<button
    type="button"
    data-vellum-search-trigger
    data-vellum-search-trigger-variant="{{ $variant }}"
    data-vellum-button
    class="{{ $classes }}"
    @if ($variant === 'icon') aria-label="Open search" @endif
    x-on:click="$dispatch('vellum-search-open')"
>
    <span class="inline-flex min-w-0 items-center gap-2">
        {!! \Vellum\Support\Icons::magnifyingGlass(['class' => 'h-4 w-4 shrink-0']) !!}
        @if ($variant !== 'icon')
            <span class="truncate">Search...</span>
        @endif
    </span>
    @if ($variant !== 'icon')
        <span
            class="{{ $variant === 'header' ? 'inline-flex items-center gap-0.5' : 'ml-auto hidden items-center gap-0.5 sm:inline-flex' }} shrink-0"
            aria-hidden="true"
            x-data="vellumHotkeyChip"
        >
            <x-vellum::ui.kbd><span x-text="mod">Ctrl</span></x-vellum::ui.kbd>
            <x-vellum::ui.kbd>{{ $hotkey }}</x-vellum::ui.kbd>
        </span>
    @endif
</button>
