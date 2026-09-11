@props([
    'variant' => 'default',
])

@php
    use Vellum\Support\Cn;

    $base = 'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold transition-colors';

    $variants = [
        'default' => 'border-transparent bg-primary text-primary-foreground',
        'secondary' => 'border-transparent bg-secondary text-secondary-foreground',
        'outline' => 'border-border text-foreground',
        'destructive' => 'border-transparent bg-destructive text-destructive-foreground',
    ];

    $classes = Cn::merge(
        $base,
        $variants[$variant] ?? $variants['default'],
        $attributes->get('class'),
    );
@endphp

<span
    data-vellum-badge
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>{{ $slot }}</span>
