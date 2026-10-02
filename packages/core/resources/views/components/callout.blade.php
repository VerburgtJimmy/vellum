@props([
    'type' => 'note',
    'title' => null,
    'name' => null,
])
@php
    $aliases = ['success' => 'tip', 'idea' => 'note'];
    $name = is_string($name) && $name !== '' ? $name : $type;
    $resolved = $aliases[$type] ?? $type;
    $allowed = ['note', 'tip', 'warning', 'danger', 'info'];
    if (! in_array($resolved, $allowed, true)) {
        $resolved = 'note';
    }
    $label = ucfirst(in_array($name, [...$allowed, ...array_keys($aliases)], true) ? $name : $resolved);
    $icon = \Vellum\Support\Icons::callout($resolved);
@endphp
<div class="vellum-callout vellum-callout-{{ $resolved }}" data-vellum-callout="{{ $name }}"><span class="vellum-callout-rail" aria-hidden="true"></span><div class="vellum-callout-body">@if (is_string($title) && $title !== '')<p class="vellum-callout-title"><span class="vellum-callout-icon vellum-callout-icon-{{ $resolved }}" aria-hidden="true">{!! $icon !!}</span><span class="vellum-callout-kind">{{ $label }}: </span>{{ $title }}</p>@else<p class="vellum-callout-title" data-vellum-callout-label><span class="vellum-callout-icon vellum-callout-icon-{{ $resolved }}" aria-hidden="true">{!! $icon !!}</span>{{ $label }}</p>@endif<div class="vellum-callout-content">{!! $slot !!}</div></div></div>
