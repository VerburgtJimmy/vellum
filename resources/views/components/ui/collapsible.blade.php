@props([
    'open' => false,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge($attributes->get('class'));
@endphp

<div
    data-vellum-collapsible
    x-data="{ open: @js((bool) $open) }"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <div
        data-vellum-collapsible-trigger
        role="button"
        tabindex="0"
        :aria-expanded="open.toString()"
        @@click="open = ! open"
        @@keydown.enter.prevent="open = ! open"
        @@keydown.space.prevent="open = ! open"
    >
        {{ $trigger ?? '' }}
    </div>
    <div
        data-vellum-collapsible-content
        x-show="open"
        x-collapse
        @if (! filter_var($open, FILTER_VALIDATE_BOOLEAN))
            x-cloak
        @endif
    >
        {{ $content ?? $slot }}
    </div>
</div>
