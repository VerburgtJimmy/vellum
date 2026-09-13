@props([
    'href' => '#',
    'title' => '',
    'icon' => null,
])
<a class="vellum-card" href="{{ $href }}" data-vellum-card="">@if (is_string($icon) && $icon !== '')<span class="vellum-card-icon" data-icon="{{ $icon }}" aria-hidden="true"></span>@endif<span class="vellum-card-title">{{ $title }}</span></a>
