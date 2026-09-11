@props([
    'type' => 'single',
    'default' => null,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge($attributes->get('class'));
    $initial = $type === 'multiple' ? [] : $default;
@endphp

<div
    data-vellum-accordion
    data-type="{{ $type }}"
    x-data="{
        type: @js($type),
        open: @js($initial),
        isOpen(value) {
            return this.type === 'multiple'
                ? this.open.includes(value)
                : this.open === value
        },
        toggle(value) {
            if (this.type === 'multiple') {
                this.open = this.isOpen(value)
                    ? this.open.filter((item) => item !== value)
                    : [...this.open, value]
                return
            }

            this.open = this.open === value ? null : value
        },
    }"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</div>
