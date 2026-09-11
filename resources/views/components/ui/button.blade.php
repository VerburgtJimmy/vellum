@props([
    'variant' => 'default',
    'size' => 'default',
    'type' => 'button',
    'href' => null,
])

@php
    use Vellum\Support\Cn;

    $base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

    $variants = [
        'default' => 'bg-primary text-primary-foreground hover:bg-primary/90',
        'ghost' => 'hover:bg-accent hover:text-accent-foreground',
        'outline' => 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        'secondary' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
    ];

    $sizes = [
        'default' => 'h-9 px-4 py-2',
        'sm' => 'h-8 rounded-md px-3 text-xs',
        'icon' => 'h-9 w-9',
    ];

    $classes = Cn::merge(
        $base,
        $variants[$variant] ?? $variants['default'],
        $sizes[$size] ?? $sizes['default'],
        $attributes->get('class'),
    );
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        data-vellum-button
        {{ $attributes->except('class')->merge(['class' => $classes]) }}
    >{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        data-vellum-button
        {{ $attributes->except('class')->merge(['class' => $classes]) }}
    >{{ $slot }}</button>
@endif
