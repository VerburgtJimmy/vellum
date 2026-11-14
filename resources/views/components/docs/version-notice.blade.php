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
                These are the docs for <strong>{{ $siteVersions->label($currentVersion) }}</strong>, which is not released yet.
            @else
                You are reading the docs for <strong>{{ $siteVersions->label($currentVersion) }}</strong>, an older version.
            @endif
        </span>
        <a href="{{ $versionHrefs[$latest] ?? '#' }}">{{ $samePage ? 'Read this page in '.$latestName : 'Go to '.$latestName }}</a>
    </div>
@endif
