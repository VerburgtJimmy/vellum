@extends('vellum::layouts.docs')

@section('title', $pageTitle ?? $document->title)

@section('content')
    @php
        $searchPlacement = $searchPlacement ?? 'sidebar';
        $hasHeader = $searchPlacement === 'header';
        $tocSticky = $hasHeader ? 'top-14 max-h-[calc(100vh-3.5rem)]' : 'top-0 max-h-screen';
        $tocMobileSticky = $hasHeader ? 'top-14' : 'top-14 md:top-0';
    @endphp

    <x-vellum::docs.shell
        :document="$document"
        :navigation="$navigation"
        :search-hash="$searchHash"
        :versions="$versions ?? []"
        :current-version="$currentVersion ?? null"
        :version-hrefs="$versionHrefs ?? []"
        :search-placement="$searchPlacement"
        :static-export="$staticExport ?? false"
    >
        <div class="flex min-w-0 min-h-0 flex-1 flex-col">
            <x-vellum::docs.toc :toc="$toc" :document="$document" placement="mobile" :sticky-class="$tocSticky" :mobile-sticky-class="$tocMobileSticky" />

            <div data-vellum-page-row class="flex min-w-0 flex-1 gap-8 px-4 py-8 md:px-6 xl:px-8">
            <main id="vellum-content" class="flex min-h-0 w-full min-w-0 max-w-[860px] flex-1 flex-col">
                <x-vellum::docs.breadcrumb :breadcrumbs="$breadcrumbs" :updated-at="$updatedAt ?? null" />

                <article data-vellum-article>
                    <h1 class="mb-2 text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                        {{ $document->title }}
                    </h1>
                    @if ($document->description)
                        <p class="mb-2 text-lg text-muted-foreground">{{ $document->description }}</p>
                    @endif
                    <x-vellum::docs.page-actions
                        :markdown-source="$markdownSource ?? ''"
                        :raw-url="$rawUrl ?? ''"
                        :edit-url="$editUrl ?? null"
                    />
                    <div class="vellum-prose">
                        {!! $html ?? $document->html !!}
                    </div>
                </article>

                <div class="mt-auto">
                    <x-vellum::docs.pagination :previous="$previous" :next="$next" />
                    <x-vellum::docs.footer />
                </div>
            </main>

            <x-vellum::docs.toc :toc="$toc" :document="$document" placement="desktop" :sticky-class="$tocSticky" />
            </div>
        </div>
    </x-vellum::docs.shell>
@endsection
