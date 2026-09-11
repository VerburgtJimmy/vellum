@props([
    'delay' => 300,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge('relative inline-flex', $attributes->get('class'));
@endphp

<div
    data-vellum-tooltip
    x-data="{
        open: false,
        timer: null,
        show() {
            clearTimeout(this.timer)
            this.timer = setTimeout(() => { this.open = true }, {{ (int) $delay }})
        },
        hide() {
            clearTimeout(this.timer)
            this.open = false
        },
    }"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <div
        data-vellum-tooltip-trigger
        x-ref="trigger"
        @@mouseenter="show()"
        @@mouseleave="hide()"
        @@focusin="show()"
        @@focusout="hide()"
    >
        {{ $trigger ?? $slot }}
    </div>
    <div
        data-vellum-tooltip-content
        x-show="open"
        x-cloak
        x-anchor.offset.6="$refs.trigger"
        role="tooltip"
        class="z-50 overflow-hidden rounded-md bg-primary px-3 py-1.5 text-xs text-primary-foreground"
    >
        {{ $content ?? '' }}
    </div>
</div>
