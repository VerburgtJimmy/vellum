@php
    $default = config('vellum.theme.default', 'system');
@endphp

<div
    data-vellum-theme-toggle
    class="relative"
    x-data="{
        open: false,
        preference: window.VellumTheme?.getStoredTheme(@js($default)) ?? @js($default),
        labels: { light: 'Light', dark: 'Dark', system: 'System' },
        options: ['light', 'dark', 'system'],
        active: 0,
        set(value) {
            this.preference = value
            window.VellumTheme?.applyTheme(value)
            this.closeMenu()
        },
        openMenu() {
            this.active = Math.max(0, this.options.indexOf(this.preference))
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
                this.active = (this.active + 1) % this.options.length
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
                return
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault()
                this.active = (this.active - 1 + this.options.length) % this.options.length
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[this.active]?.focus()
                return
            }
            if (event.key === 'Home') {
                event.preventDefault()
                this.active = 0
                this.$refs.menu?.querySelectorAll('[role=menuitem]')[0]?.focus()
                return
            }
            if (event.key === 'End') {
                event.preventDefault()
                this.active = this.options.length - 1
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
        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label="Toggle theme"
        aria-haspopup="menu"
        :aria-expanded="open.toString()"
        x-on:click="toggle()"
        x-on:keydown="onTriggerKeydown($event)"
    >
        <svg x-show="preference !== 'dark'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
        </svg>
        <svg x-cloak x-show="preference === 'dark'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
        </svg>
    </button>

    <div
        x-ref="menu"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="absolute right-0 top-full z-50 mt-1 min-w-[8rem] rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
        role="menu"
        aria-label="Color theme"
        x-on:keydown="onMenuKeydown($event)"
    >
        <template x-for="(value, index) in options" :key="value">
            <button
                type="button"
                role="menuitem"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :class="preference === value && 'bg-accent'"
                :tabindex="active === index ? 0 : -1"
                x-on:click="set(value)"
                x-on:mouseenter="active = index"
                x-text="labels[value]"
            ></button>
        </template>
    </div>
</div>
