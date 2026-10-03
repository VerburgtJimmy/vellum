@props([
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
    'versionPages' => null,
    'variant' => 'compact',
])

@php
    use Vellum\Support\Icons;
    use Vellum\Support\VersionLabel;

    $enabled = (bool) config('vellum.versions.enabled');
    $versions = is_array($versions) ? array_values(array_filter($versions, 'is_string')) : [];
    $versionHrefs = is_array($versionHrefs) ? $versionHrefs : [];
    $currentVersion = is_string($currentVersion) && $currentVersion !== ''
        ? $currentVersion
        : ($versions[0] ?? null);
    $labels = VersionLabel::map($versions);
    // Null when the caller does not know which versions have this page: none is marked.
    $has = static fn (string $version): bool => ! is_array($versionPages) || in_array($version, $versionPages, true);
    $tags = ['latest' => 'Latest', 'unreleased' => 'Unreleased'];
    $wide = $variant === 'wide';
    static $menuSeq = 0;
    $menuId = 'vellum-version-menu-'.(++$menuSeq);
@endphp

@if ($enabled && $versions !== [] && $currentVersion !== null)
<div
    data-vellum-version-switcher
    data-vellum-version-switcher-variant="{{ $wide ? 'wide' : 'compact' }}"
    class="relative"
    x-data="{
        open: false,
        count: {{ count($versions) }},
        active: {{ max(0, (int) array_search($currentVersion, $versions, true)) }},
        current: {{ max(0, (int) array_search($currentVersion, $versions, true)) }},
        focusItem() {
            this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
        },
        openMenu() {
            this.active = this.current
            this.open = true
            this.$nextTick(() => this.focusItem())
        },
        closeMenu() {
            this.open = false
            this.$nextTick(() => this.$refs.trigger?.focus())
        },
        toggle() {
            if (this.open) {
                this.closeMenu()
            } else {
                this.openMenu()
            }
        },
        onTriggerKeydown(event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                this.openMenu()
            }
        },
        onMenuKeydown(event) {
            const move = { ArrowDown: this.active + 1, ArrowUp: this.active - 1, Home: 0, End: this.count - 1 }[event.key]

            if (event.key === 'Escape') {
                event.preventDefault()
                this.closeMenu()
            } else if (move !== undefined) {
                event.preventDefault()
                this.active = (move + this.count) % this.count
                this.focusItem()
            }
        },
    }"
    x-on:keydown.escape.window="if (open) closeMenu()"
    x-on:click.outside="if (open) closeMenu()"
>
    <button
        type="button"
        x-ref="trigger"
        data-vellum-button
        @class([
            'inline-flex items-center rounded-md border text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            'h-9 w-full justify-between gap-2 border-input bg-background px-2.5 text-sm' => $wide,
            'h-8 gap-1 border-border bg-background px-2 text-xs font-medium' => ! $wide,
        ])
        aria-label="Documentation version: {{ $labels[$currentVersion] ?? $currentVersion }}"
        aria-haspopup="menu"
        aria-controls="{{ $menuId }}"
        :aria-expanded="open.toString()"
        x-on:click="toggle()"
        x-on:keydown="onTriggerKeydown($event)"
    >
        <span class="inline-flex min-w-0 items-center gap-2">
            <span class="truncate">{{ $labels[$currentVersion] ?? $currentVersion }}</span>
            @if ($wide && isset($tags[VersionLabel::kind($currentVersion)]))
                <span class="vellum-version-tag" data-kind="{{ VersionLabel::kind($currentVersion) }}">{{ $tags[VersionLabel::kind($currentVersion)] }}</span>
            @endif
        </span>
        {!! Icons::caretDown(['class' => 'h-3.5 w-3.5 shrink-0 opacity-70']) !!}
    </button>

    <div
        x-ref="menu"
        x-cloak
        x-show="open"
        x-transition.opacity
        id="{{ $menuId }}"
        @class([
            'absolute left-0 top-full z-50 mt-1 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md',
            'w-full' => $wide,
            'min-w-[13rem]' => ! $wide,
        ])
        role="menu"
        aria-label="Documentation versions"
        x-on:keydown="onMenuKeydown($event)"
    >
        @foreach ($versions as $index => $version)
            @php
                $href = is_string($versionHrefs[$version] ?? null) ? $versionHrefs[$version] : '#';
                $kind = VersionLabel::kind($version);
            @endphp
            <a
                href="{{ $href }}"
                role="menuitem"
                class="block w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {{ $version === $currentVersion ? 'bg-accent' : '' }}"
                :tabindex="active === {{ $index }} ? 0 : -1"
                x-on:mouseenter="active = {{ $index }}"
                @if ($version === $currentVersion) aria-current="true" @endif
            >
                <span class="flex items-center gap-2">
                    <span class="truncate">{{ $labels[$version] ?? $version }}</span>
                    @isset($tags[$kind])
                        <span class="vellum-version-tag" data-kind="{{ $kind }}">{{ $tags[$kind] }}</span>
                    @endisset
                </span>
                @if (! $has($version))
                    <span class="mt-0.5 block text-xs text-muted-foreground">This page is not in {{ $labels[$version] ?? $version }}. Opens its start page.</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endif
