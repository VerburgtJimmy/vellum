@props([
    'default' => null,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge($attributes->get('class'));
@endphp

<div
    data-vellum-tabs
    x-data="vellumTabs(@js($default))"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
