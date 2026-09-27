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
    $icon = \Vellum\Support\Icons::callout($resolved);
@endphp
<div class="vellum-callout vellum-callout-{{ $resolved }}" data-vellum-callout="{{ $name }}"><span class="vellum-callout-rail" aria-hidden="true"></span><span class="vellum-callout-icon vellum-callout-icon-{{ $resolved }}" aria-hidden="true">{!! $icon !!}</span><div class="vellum-callout-body">@if (is_string($title) && $title !== '')<p class="vellum-callout-title">{{ $title }}</p>@endif<div class="vellum-callout-content">{!! $slot !!}</div></div></div>
