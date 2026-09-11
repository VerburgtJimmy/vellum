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
    x-show="active === @js($value)"
    x-cloak
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
