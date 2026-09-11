@props([
    'open' => false,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge($attributes->get('class'));
@endphp

<div
    data-vellum-dialog
    x-data="{
        open: @js((bool) $open),
        show() { this.open = true },
        hide() { this.open = false },
    }"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''"
    @@keydown.escape.window="if (open) hide()"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <div data-vellum-dialog-trigger @@click="show()">
        {{ $trigger ?? '' }}
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50"
            role="presentation"
        >
            <div
                data-vellum-dialog-overlay
                class="fixed inset-0 bg-black/50"
                @@click="hide()"
                aria-hidden="true"
            ></div>
            <div
                data-vellum-dialog-panel
                role="dialog"
                aria-modal="true"
                x-trap.noscroll="open"
                @@click.stop
                class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 border border-border bg-background p-6 shadow-lg rounded-lg"
            >
                @if (isset($title) || isset($description))
                    <div class="flex flex-col gap-1.5 text-center sm:text-left">
                        @isset($title)
                            <h2 data-vellum-dialog-title class="text-lg font-semibold leading-none tracking-tight">
                                {{ $title }}
                            </h2>
                        @endisset
                        @isset($description)
                            <p data-vellum-dialog-description class="text-sm text-muted-foreground">
                                {{ $description }}
                            </p>
                        @endisset
                    </div>
                @endif

                <div data-vellum-dialog-content>
                    {{ $content ?? $slot }}
                </div>

                @isset($footer)
                    <div data-vellum-dialog-footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </template>
</div>
