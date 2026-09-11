@props([
    'delay' => 300,
])

@php
    use Vellum\Support\Cn;

    $classes = Cn::merge('relative inline-flex', $attributes->get('class'));
@endphp

<div
    data-vellum-tooltip
    x-data="vellumTooltip({{ (int) $delay }})"
    x-on:pointerenter="ensureAnchor()"
    x-on:focusin="ensureAnchor()"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    <div
        data-vellum-tooltip-trigger
        x-ref="trigger"
        x-on:mouseenter="show()"
        x-on:mouseleave="hide()"
        x-on:focusin="show()"
        x-on:focusout="hide()"
    >
        {{ $trigger ?? $slot }}
    </div>

    {{-- x-if mounts only when open so x-anchor is not evaluated on a display:none node --}}
    <template x-if="open">
        <div
            data-vellum-tooltip-content
            x-ref="content"
            x-anchor.offset.6="$refs.trigger"
            role="tooltip"
            class="absolute z-50 overflow-hidden rounded-md bg-primary px-3 py-1.5 text-xs text-primary-foreground"
        >
            {{ $content ?? '' }}
        </div>
    </template>
</div>
