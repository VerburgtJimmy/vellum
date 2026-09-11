@props([
    'open' => false,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge('relative inline-block', $attributes->get('class'));
@endphp

<div
    data-vellum-popover
    x-data="{ open: @js((bool) $open) }"
    @@keydown.escape.window="if (open) open = false"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <div
        data-vellum-popover-trigger
        x-ref="trigger"
        @@click="open = ! open"
    >
        {{ $trigger ?? '' }}
    </div>
    <div
        data-vellum-popover-content
        x-show="open"
        x-cloak
        x-anchor.offset.8="$refs.trigger"
        @@click.outside="open = false"
        role="dialog"
        class="z-50 w-72 rounded-md border border-border bg-popover p-4 text-popover-foreground shadow-md outline-none"
    >
        {{ $content ?? $slot }}
    </div>
</div>
