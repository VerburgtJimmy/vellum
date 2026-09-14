@props([
    'value',
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge(
        'mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        $attributes->get('class'),
    );
@endphp

<div
    role="tabpanel"
    data-vellum-tabs-content
    data-value="{{ $value }}"
    :id="{{ '$id' }}('panel-' + @js($value))"
    :aria-labelledby="{{ '$id' }}('tab-' + @js($value))"
    :aria-hidden="(active !== @js($value)).toString()"
    x-show="active === @js($value)"
    x-cloak
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
