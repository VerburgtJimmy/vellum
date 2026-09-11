@php
    use Vellum\Support\Cn;

    $classes = Cn::merge('vellum-scroll-area relative', $attributes->get('class'));
@endphp

<div
    data-vellum-scroll-area
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
