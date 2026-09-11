@php
    use Vellum\Support\Cn;

    $classes = Cn::merge(
        'pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border border-border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground',
        $attributes->get('class'),
    );
@endphp

<kbd
    data-vellum-kbd
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>{{ $slot }}</kbd>
