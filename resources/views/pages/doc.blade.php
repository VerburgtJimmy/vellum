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
        :sections="$sections ?? []"
        :versions="$versions ?? []"
        :current-version="$currentVersion ?? null"
        :version-hrefs="$versionHrefs ?? []"
        :version-pages="$versionPages ?? null"
        :search-placement="$searchPlacement"
        :static-export="$staticExport ?? false"
    >
        <div class="flex min-w-0 min-h-0 flex-1 flex-col">
            <x-vellum::docs.toc :toc="$toc" :document="$document" placement="mobile" :sticky-class="$tocSticky" :mobile-sticky-class="$tocMobileSticky" />

            <div data-vellum-page-row class="flex min-w-0 flex-1 gap-8 px-4 py-8 md:px-6 xl:px-8">
            <main id="vellum-content" class="flex min-h-0 w-full min-w-0 max-w-[860px] flex-1 flex-col">
                <x-vellum::docs.version-notice
                    :versions="$versions ?? []"
                    :current-version="$currentVersion ?? null"
                    :version-hrefs="$versionHrefs ?? []"
                    :version-pages="$versionPages ?? null"
                />
                <x-vellum::docs.breadcrumb :breadcrumbs="$breadcrumbs" />

                <article data-vellum-article>
                    @php
                        // With no date and no reading time the actions would have a
                        // row to themselves, so they sit beside the title instead.
                        $facts = ($updated ?? null) !== null || ($readingMinutes ?? 0) >= 3;
                    @endphp
                    <header @class(['grid gap-x-4', 'mb-9 sm:grid-cols-[minmax(0,1fr)_auto]' => ! $facts])>
                        <h1 class="mb-2 min-w-0 text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                            {{ $document->title }}
                        </h1>
                        @unless ($facts)
                            {{-- Beside the title where there is room, under the description where there is not. --}}
                            <div data-vellum-title-actions class="row-start-3 -ml-2 mt-2 justify-self-start sm:row-start-1 sm:col-start-2 sm:ml-0 sm:mt-0.5 sm:justify-self-end md:mt-1">
                                <x-vellum::docs.page-actions
                                    :markdown-source="$markdownSource ?? ''"
                                    :raw-url="$rawUrl ?? ''"
                                    :edit-url="$editUrl ?? null"
                                />
                            </div>
                        @endunless
                        @if ($document->description)
                            <p @class(['text-lg text-muted-foreground', 'sm:col-span-2' => ! $facts])>{{ $document->description }}</p>
                        @endif
                    </header>
                    @if ($facts)
                        <x-vellum::docs.page-meta :updated="$updated ?? null" :reading-minutes="$readingMinutes ?? 0">
                            <x-vellum::docs.page-actions
                                :markdown-source="$markdownSource ?? ''"
                                :raw-url="$rawUrl ?? ''"
                                :edit-url="$editUrl ?? null"
                            />
                        </x-vellum::docs.page-meta>
                    @endif
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
