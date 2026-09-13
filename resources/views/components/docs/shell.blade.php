@props([
    'document' => null,
    'navigation' => [],
    'searchHash' => null,
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
    'searchPlacement' => 'sidebar',
    'staticExport' => false,
])

@php
    $searchInSidebar = $searchPlacement !== 'header';
    $searchEnabled = (bool) config('vellum.search.enabled');
    $hasHeader = ! $searchInSidebar;
@endphp

<div data-vellum-docs class="flex min-h-screen flex-col" x-data="vellumChrome">
    @if ($searchEnabled)
        <x-vellum::docs.search
            :search-hash="$searchHash"
            :current-version="$currentVersion ?? null"
            :static-export="$staticExport ?? false"
        />
    @endif

    <div
        data-vellum-sidebar-pill
        class="fixed left-4 z-50 items-center gap-0.5 rounded-xl border border-border bg-muted p-0.5 text-muted-foreground shadow-lg motion-safe:transition-opacity"
    >
        <button
            type="button"
            data-vellum-sidebar-expand
            data-vellum-button
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="Expand sidebar"
            x-on:click="toggleSidebar()"
        >
            {!! \Vellum\Support\Icons::sidebar(['class' => 'h-4 w-4']) !!}
        </button>
        @if ($searchEnabled)
            <x-vellum::docs.search-trigger variant="icon" />
        @endif
    </div>

    <div
        data-vellum-sidebar-hotzone
        class="fixed inset-y-0 left-0 z-[46] hidden w-4 md:block"
        aria-hidden="true"
        x-on:mouseenter="showPeek()"
        x-on:mouseleave="scheduleHidePeek()"
    ></div>

    @if ($hasHeader)
        <x-vellum::docs.header
            :document="$document"
            :navigation="$navigation"
            :search-hash="$searchHash"
            :versions="$versions"
            :current-version="$currentVersion"
            :version-hrefs="$versionHrefs"
        />
    @else
        <div
            data-vellum-mobile-bar
            class="sticky top-0 z-40 flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border bg-background/95 px-4 backdrop-blur md:hidden"
        >
            <x-vellum::docs.brand />
            <div class="flex items-center gap-0.5">
                @if ($searchEnabled)
                    <x-vellum::docs.search-trigger variant="icon" />
                @endif
                <x-vellum::docs.mobile-sidebar
                    :navigation="$navigation"
                    :document="$document"
                    :search-in-sidebar="$searchInSidebar"
                    :search-hash="$searchHash"
                    :versions="$versions"
                    :current-version="$currentVersion"
                    :version-hrefs="$versionHrefs"
                />
            </div>
        </div>
    @endif

    <div class="flex w-full flex-1">
        <div
            data-vellum-sidebar-wrap
            class="hidden shrink-0 md:block {{ $hasHeader ? 'sticky top-14 h-[calc(100vh-3.5rem)]' : 'sticky top-0 h-screen' }}"
            x-on:mouseenter="showPeek()"
            x-on:mouseleave="scheduleHidePeek()"
        >
            <x-vellum::docs.sidebar
                :navigation="$navigation"
                :document="$document"
                :search-in-sidebar="$searchInSidebar"
                :search-hash="$searchHash"
                :versions="$versions"
                :current-version="$currentVersion"
                :version-hrefs="$versionHrefs"
                :show-brand="$searchInSidebar"
            />
        </div>

        {{ $slot }}
    </div>
</div>
