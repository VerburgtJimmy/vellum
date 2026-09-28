@props([
    'updated' => null,
    'readingMinutes' => 0,
])

@php
    $showReading = $readingMinutes >= 3;
@endphp

@if ($updated || $showReading || $slot->isNotEmpty())
    <div data-vellum-page-meta class="mt-4 mb-9 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <p class="vellum-page-facts">
            @if ($updated)
                <time data-vellum-updated datetime="{{ $updated['iso'] }}" title="{{ $updated['long'] }}">Updated {{ $updated['label'] }}</time>
            @endif
            @if ($showReading)
                <span>{{ $readingMinutes }} min read</span>
            @endif
            @if ($updated)
                <span data-vellum-page-changed class="vellum-page-changed" hidden>Changed since your last visit</span>
            @endif
        </p>
        {{ $slot }}
    </div>
@endif
