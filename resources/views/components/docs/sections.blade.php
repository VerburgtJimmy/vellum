@props([
    'sections' => [],
    'class' => '',
])

{{-- The sidebars a reader can move between: the docs' own, each root folder
     and the API reference. The first thing in every one of them. --}}
@php
    use Vellum\Support\Icons;

    // Design round only: ?switcher=segmented|dropdown|list picks a variant
    // in the local environment. Removed once one is chosen.
    $variant = app()->environment('local') && in_array(request()->query('switcher'), ['segmented', 'dropdown', 'list'], true)
        ? request()->query('switcher')
        : 'segmented';
    $current = collect($sections)->firstWhere('current', true) ?? $sections[0];
    $currentIndex = max(0, (int) array_search(true, array_column($sections, 'current'), true));
    static $sectionsSeq = 0;
    $menuId = 'vellum-sections-menu-'.(++$sectionsSeq);
@endphp

@if ($variant === 'segmented')
    <nav aria-label="Sections" data-vellum-sections class="vellum-sections {{ $class }}">
        @foreach ($sections as $section)
            <a
                href="{{ $section['href'] }}"
                class="vellum-section"
                @if ($section['current']) aria-current="true" @endif
            >{{ $section['title'] }}</a>
        @endforeach
    </nav>
@elseif ($variant === 'list')
    <nav aria-label="Sections" data-vellum-sections data-vellum-sections-variant="list" class="vellum-sections-list {{ $class }}">
        @foreach ($sections as $section)
            <a
                href="{{ $section['href'] }}"
                class="vellum-sections-item"
                @if ($section['current']) aria-current="true" @endif
            >
                <span class="vellum-sections-title">{{ $section['title'] }}</span>
                @if ($section['description'])
                    <span class="vellum-sections-description">{{ $section['description'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>
@else
    <nav
        aria-label="Sections"
        data-vellum-sections
        data-vellum-sections-variant="dropdown"
        class="vellum-sections-dropdown relative {{ $class }}"
        x-data="{
            open: false,
            count: {{ count($sections) }},
            active: {{ $currentIndex }},
            focusItem() {
                this.$refs.menu?.querySelectorAll('a')[this.active]?.focus()
            },
            openMenu() {
                this.active = {{ $currentIndex }}
                this.open = true
                this.$nextTick(() => this.focusItem())
            },
            closeMenu() {
                this.open = false
                this.$nextTick(() => this.$refs.trigger?.focus())
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
            class="vellum-sections-trigger"
            aria-haspopup="true"
            aria-controls="{{ $menuId }}"
            :aria-expanded="open.toString()"
            x-on:click="open ? closeMenu() : openMenu()"
            x-on:keydown.arrow-down.prevent="openMenu()"
        >
            <span class="vellum-sections-text">
                <span class="vellum-sections-title">{{ $current['title'] }}</span>
                @if ($current['description'])
                    <span class="vellum-sections-description">{{ $current['description'] }}</span>
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
            class="vellum-sections-menu"
            x-on:keydown="onMenuKeydown($event)"
        >
            @foreach ($sections as $index => $section)
                <a
                    href="{{ $section['href'] }}"
                    class="vellum-sections-item"
                    :tabindex="active === {{ $index }} ? 0 : -1"
                    x-on:mouseenter="active = {{ $index }}"
                    @if ($section['current']) aria-current="true" @endif
                >
                    <span class="vellum-sections-title">{{ $section['title'] }}</span>
                    @if ($section['description'])
                        <span class="vellum-sections-description">{{ $section['description'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Without script the menu cannot open, so the links are kept here. --}}
        <noscript>
            <div class="vellum-sections-list">
                @foreach ($sections as $section)
                    <a href="{{ $section['href'] }}" class="vellum-sections-item" @if ($section['current']) aria-current="true" @endif>
                        <span class="vellum-sections-title">{{ $section['title'] }}</span>
                    </a>
                @endforeach
            </div>
        </noscript>
    </nav>
@endif
