@props([
    'persist' => null,
    'code' => false,
    'tabs' => [],
])
@php
    $persistKey = is_string($persist) && $persist !== '' ? $persist : null;
    $tabList = is_array($tabs) ? $tabs : [];
    $defaultId = $tabList[0]['id'] ?? '0';
    $class = $code ? 'vellum-tabs vellum-tabs-code' : 'vellum-tabs';
    static $groupSeq = 0;
    $groupId = 'vt'.(++$groupSeq);
@endphp
@if ($tabList === [])
<div class="{{ $class }}" data-vellum-tabs=""@if ($persistKey) data-persist="{{ $persistKey }}"@endif>{!! $slot !!}</div>
@else
<div
    class="{{ $class }}"
    data-vellum-tabs
    x-data="vellumTabs(@js($defaultId), @js($persistKey))"
    @if ($persistKey) data-persist="{{ $persistKey }}" @endif
>
    <div class="vellum-tabs-list" role="tablist" aria-orientation="horizontal" x-on:keydown="onListKeydown($event)">
        @foreach ($tabList as $tab)
            @php
                $tabDomId = $groupId.'-tab-'.$tab['id'];
                $panelDomId = $groupId.'-panel-'.$tab['id'];
            @endphp
            <button
                type="button"
                class="vellum-tabs-trigger"
                role="tab"
                id="{{ $tabDomId }}"
                data-value="{{ $tab['id'] }}"
                aria-controls="{{ $panelDomId }}"
                :aria-selected="(active === @js($tab['id'])).toString()"
                :tabindex="active === @js($tab['id']) ? 0 : -1"
                x-on:click="select(@js($tab['id']))"
            >{{ $tab['label'] }}</button>
        @endforeach
    </div>
    @foreach ($tabList as $index => $tab)
        @php
            $tabDomId = $groupId.'-tab-'.$tab['id'];
            $panelDomId = $groupId.'-panel-'.$tab['id'];
        @endphp
        <div
            class="vellum-tabs-panel"
            role="tabpanel"
            id="{{ $panelDomId }}"
            data-value="{{ $tab['id'] }}"
            aria-labelledby="{{ $tabDomId }}"
            :aria-hidden="(active !== @js($tab['id'])).toString()"
            x-show="active === @js($tab['id'])"
            @if ($index !== 0) x-cloak @endif
        >{!! $tab['html'] !!}</div>
    @endforeach
</div>
@endif
