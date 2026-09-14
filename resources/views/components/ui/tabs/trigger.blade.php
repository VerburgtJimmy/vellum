@props([
    'value',
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge(
        'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow',
        $attributes->get('class'),
    );
@endphp

<button
    type="button"
    role="tab"
    data-vellum-tabs-trigger
    data-value="{{ $value }}"
    :id="{{ '$id' }}('tab-' + @js($value))"
    :aria-controls="{{ '$id' }}('panel-' + @js($value))"
    :data-state="active === @js($value) ? 'active' : 'inactive'"
    :aria-selected="(active === @js($value)).toString()"
    :tabindex="active === @js($value) ? 0 : -1"
    @@click="select(@js($value))"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</button>
