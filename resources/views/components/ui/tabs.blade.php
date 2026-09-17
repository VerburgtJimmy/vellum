@props([
    'default' => null,
    // Remembers the chosen tab under this key, so picking a language once
    // keeps it picked on every other page. The runtime already supported it.
    'persist' => null,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge($attributes->get('class'));
@endphp

<div
    data-vellum-tabs
    x-data="vellumTabs(@js($default), @js($persist))"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
