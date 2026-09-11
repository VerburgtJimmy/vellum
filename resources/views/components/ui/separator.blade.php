@props([
    'orientation' => 'horizontal',
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge(
        'shrink-0 bg-border',
        $orientation === 'vertical' ? 'h-full w-px' : 'h-px w-full',
        $attributes->get('class'),
    );
@endphp

<div
    data-vellum-separator
    role="separator"
    aria-orientation="{{ $orientation }}"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
></div>
