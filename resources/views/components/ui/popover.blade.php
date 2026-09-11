@props([
    'open' => false,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge('relative inline-block', $attributes->get('class'));
@endphp

<div
    data-vellum-popover
    x-data="vellumPopover(@js((bool) $open))"
    x-on:pointerenter="ensureAnchor()"
    x-on:focusin="ensureAnchor()"
    x-on:keydown.escape.window="if (open) close()"
    x-on:click.outside="close()"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <div
        data-vellum-popover-trigger
        x-ref="trigger"
        x-on:click="toggle()"
    >
        {{ $trigger ?? '' }}
    </div>

    {{-- x-if mounts content only when open so floating-ui can measure (not display:none) --}}
    <template x-if="open">
        <div
            data-vellum-popover-content
            x-ref="content"
            x-anchor.offset.8="$refs.trigger"
            role="dialog"
            class="absolute z-50 w-72 rounded-md border border-border bg-popover p-4 text-popover-foreground shadow-md outline-none"
        >
            {{ $content ?? $slot }}
        </div>
    </template>
</div>
