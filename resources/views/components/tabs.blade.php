@props([
    'persist' => null,
    'code' => false,
    'tabs' => [],
])
@php
    $persistKey = is_string($persist) && $persist !== '' ? $persist : null;
    $tabList = is_array($tabs) ? $tabs : [];
    $defaultId = $tabList[0]['id'] ?? '0';
    $storageKey = $persistKey !== null ? 'vellum-tabs-'.$persistKey : null;
    if ($storageKey !== null) {
        $xData = '{ active: (typeof localStorage !== "undefined" && localStorage.getItem("'.$storageKey.'")) || "'.$defaultId.'", set(id) { this.active = id; localStorage.setItem("'.$storageKey.'", id) } }';
    } else {
        $xData = '{ active: "'.$defaultId.'", set(id) { this.active = id } }';
    }
    $class = $code ? 'vellum-tabs vellum-tabs-code' : 'vellum-tabs';
@endphp
@if ($tabList === [])
<div class="{{ $class }}" data-vellum-tabs=""@if ($persistKey) data-persist="{{ $persistKey }}"@endif>{!! $slot !!}</div>
@else
<div class="{{ $class }}" data-vellum-tabs="" x-data="{{ $xData }}"@if ($persistKey) data-persist="{{ $persistKey }}"@endif><div class="vellum-tabs-list" role="tablist">@foreach ($tabList as $tab)<button type="button" class="vellum-tabs-trigger" role="tab" :aria-selected="active === '{{ $tab['id'] }}'" @@click="set('{{ $tab['id'] }}')" id="vellum-tab-{{ $tab['id'] }}">{{ $tab['label'] }}</button>@endforeach</div>@foreach ($tabList as $index => $tab)<div class="vellum-tabs-panel" role="tabpanel" x-show="active === '{{ $tab['id'] }}'" aria-labelledby="vellum-tab-{{ $tab['id'] }}"@if ($index !== 0 || $storageKey !== null) x-cloak=""@endif>{!! $tab['html'] !!}</div>@endforeach</div>
@endif
