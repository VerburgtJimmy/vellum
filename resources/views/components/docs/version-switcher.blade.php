@props([
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
])

@php
    $enabled = (bool) config('vellum.versions.enabled');
    $versions = is_array($versions) ? array_values(array_filter($versions, 'is_string')) : [];
    $versionHrefs = is_array($versionHrefs) ? $versionHrefs : [];
    $currentVersion = is_string($currentVersion) && $currentVersion !== ''
        ? $currentVersion
        : ($versions[0] ?? null);
    $labels = \Vellum\Support\VersionLabel::map($versions);
    static $menuSeq = 0;
    $menuId = 'vellum-version-menu-'.(++$menuSeq);
@endphp

@if ($enabled && $versions !== [] && $currentVersion !== null)
<div
    data-vellum-version-switcher
    class="relative"
    x-data="{
        open: false,
        versions: @js($versions),
        labels: @js($labels),
        current: @js($currentVersion),
        active: 0,
        openMenu() {
            this.active = Math.max(0, this.versions.indexOf(this.current))
            this.open = true
            this.$nextTick(() => this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus())
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
            if (event.key === 'Escape') {
                event.preventDefault()
                this.closeMenu()
                return
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault()
                this.active = (this.active + 1) % this.versions.length
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
                return
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault()
                this.active = (this.active - 1 + this.versions.length) % this.versions.length
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
                return
            }
            if (event.key === 'Home') {
                event.preventDefault()
                this.active = 0
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
                return
            }
            if (event.key === 'End') {
                event.preventDefault()
                this.active = this.versions.length - 1
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
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
        class="inline-flex h-8 items-center gap-1 rounded-md border border-border bg-background px-2 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label="Select documentation version"
        aria-haspopup="menu"
        aria-controls="{{ $menuId }}"
        :aria-expanded="open.toString()"
        x-on:click="toggle()"
        x-on:keydown="onTriggerKeydown($event)"
    >
        <span x-text="labels[current] ?? current">{{ $labels[$currentVersion] ?? $currentVersion }}</span>
        {!! \Vellum\Support\Icons::caretDown(['class' => 'h-3.5 w-3.5 opacity-70']) !!}
    </button>

    <div
        x-ref="menu"
        x-cloak
        x-show="open"
        x-transition.opacity
        id="{{ $menuId }}"
        class="absolute left-0 top-full z-50 mt-1 min-w-[6.5rem] rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
        role="menu"
        aria-label="Documentation versions"
        x-on:keydown="onMenuKeydown($event)"
    >
        @foreach ($versions as $index => $version)
            @php
                $href = is_string($versionHrefs[$version] ?? null) ? $versionHrefs[$version] : '#';
            @endphp
            <a
                href="{{ $href }}"
                role="menuitem"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {{ $version === $currentVersion ? 'bg-accent' : '' }}"
                :tabindex="active === {{ $index }} ? 0 : -1"
                x-on:mouseenter="active = {{ $index }}"
                @if ($version === $currentVersion) aria-current="true" @endif
            >{{ $labels[$version] ?? $version }}</a>
        @endforeach
    </div>
</div>
@endif
