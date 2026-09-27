@props([
    'persist' => null,
    'code' => false,
    'tabs' => [],
])
{{-- The WAI-ARIA tabs pattern with the first panel open. Switching needs a
     script; the full package replaces this view with one that has it. --}}
@php
    $persistKey = is_string($persist) && $persist !== '' ? $persist : null;
    $tabList = is_array($tabs) ? $tabs : [];
    $class = $code ? 'vellum-tabs vellum-tabs-code' : 'vellum-tabs';
    static $groupSeq = 0;
    $groupId = 'vt'.(++$groupSeq);
@endphp
@if ($tabList === [])
<div class="{{ $class }}" data-vellum-tabs=""@if ($persistKey) data-persist="{{ $persistKey }}"@endif>{!! $slot !!}</div>
@else
<div class="{{ $class }}" data-vellum-tabs=""@if ($persistKey) data-persist="{{ $persistKey }}"@endif>
    <div class="vellum-tabs-list" role="tablist" aria-orientation="horizontal">
        @foreach ($tabList as $index => $tab)
            <button type="button" class="vellum-tabs-trigger" role="tab" id="{{ $groupId }}-tab-{{ $tab['id'] }}" data-value="{{ $tab['id'] }}" aria-controls="{{ $groupId }}-panel-{{ $tab['id'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}">{{ $tab['label'] }}</button>
        @endforeach
    </div>
    @foreach ($tabList as $index => $tab)
        <div class="vellum-tabs-panel" role="tabpanel" id="{{ $groupId }}-panel-{{ $tab['id'] }}" data-value="{{ $tab['id'] }}" aria-labelledby="{{ $groupId }}-tab-{{ $tab['id'] }}"@if ($index !== 0) hidden @endif>{!! $tab['html'] !!}</div>
    @endforeach
</div>
@endif
