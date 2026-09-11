@props([
    'value',
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge('border-b border-border', $attributes->get('class'));
@endphp

<div
    data-vellum-accordion-item
    data-value="{{ $value }}"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <h3 class="flex">
        <button
            type="button"
            data-vellum-accordion-trigger
            class="flex flex-1 items-center justify-between py-4 text-sm font-medium transition-all hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :aria-expanded="isOpen(@js($value)).toString()"
            @@click="toggle(@js($value))"
        >
            {{ $trigger ?? $slot }}
        </button>
    </h3>
    <div data-vellum-accordion-content x-show="isOpen(@js($value))" x-collapse x-cloak>
        <div class="pb-4 pt-0 text-sm text-muted-foreground">
            {{ $content ?? '' }}
        </div>
    </div>
</div>
