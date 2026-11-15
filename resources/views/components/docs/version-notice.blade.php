@props([
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
    'versionPages' => null,
])

{{-- Shown on every version but the latest, so a reader who arrives on an old
     or unreleased page knows, and can go to the same page in the latest. --}}
@php
    $siteVersions = app(\Vellum\Site::class)->versions;
    $latest = config('vellum.versions.latest');
    $show = (bool) config('vellum.versions.enabled')
        && is_string($currentVersion) && $currentVersion !== ''
        && is_string($latest) && in_array($latest, $versions, true)
        && $currentVersion !== $latest;
@endphp

@if ($show)
    @php
        $kind = $siteVersions->kind($currentVersion);
        $samePage = ! is_array($versionPages) || in_array($latest, $versionPages, true);
        $latestName = $siteVersions->label($latest);
    @endphp
    <div data-vellum-version-notice="{{ $kind }}" role="note" class="vellum-version-notice">
        <span>
            @if ($kind === 'unreleased')
                {!! __('vellum::versions.notice_unreleased', ['version' => '<strong>'.e($siteVersions->label($currentVersion)).'</strong>']) !!}
            @else
                {!! __('vellum::versions.notice_older', ['version' => '<strong>'.e($siteVersions->label($currentVersion)).'</strong>']) !!}
            @endif
        </span>
        <a href="{{ $versionHrefs[$latest] ?? '#' }}">{{ $samePage ? __('vellum::versions.read_this_page_in', ['version' => $latestName]) : __('vellum::versions.go_to', ['version' => $latestName]) }}</a>
    </div>
@endif
