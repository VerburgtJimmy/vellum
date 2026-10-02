@props([
    'href' => '#',
    'title' => '',
    'icon' => null,
    'description' => null,
    'describe' => false,
])
@php
    $safeHref = \Vellum\Support\SafeHref::of((string) $href);
@endphp
<a class="vellum-card" href="{{ $safeHref }}" data-vellum-card="">@if (is_string($icon) && $icon !== '')<span class="vellum-card-icon" data-icon="{{ $icon }}" aria-hidden="true"></span>@endif<span class="vellum-card-title">{{ $title }}</span>@if (is_string($description) && $description !== '')<span class="vellum-card-description">{{ $description }}</span>@elseif ($describe)<span class="vellum-card-description" data-vellum-card-describe="{{ $safeHref }}"></span>@endif<span class="vellum-card-arrow" aria-hidden="true">{!! \Vellum\Support\Icons::arrowRight() !!}</span></a>
