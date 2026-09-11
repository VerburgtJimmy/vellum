@php
    use Vellum\Support\Cn;

    $classes = Cn::merge(
        'inline-flex h-9 items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground',
        $attributes->get('class'),
    );
@endphp

<div
    data-vellum-tabs-list
    role="tablist"
    @@keydown="onListKeydown($event)"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
