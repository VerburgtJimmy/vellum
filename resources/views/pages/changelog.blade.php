@extends('vellum::layouts.docs')

@section('title', $pageTitle ?? $changelog->title)

@section('head')
    <link rel="alternate" type="application/atom+xml" title="{{ $changelog->title }}" href="{{ $feedUrl }}">
@endsection

@section('content')
    @php
        $searchPlacement = $searchPlacement ?? 'sidebar';
        $hasHeader = $searchPlacement === 'header';
        $tocSticky = $hasHeader ? 'top-14 max-h-[calc(100vh-3.5rem)]' : 'top-0 max-h-screen';
        $tocMobileSticky = $hasHeader ? 'top-14' : 'top-14 md:top-0';
    @endphp

    <x-vellum::docs.shell
        :document="$document ?? null"
        :navigation="$navigation"
        :search-hash="$searchHash"
        :versions="$versions ?? []"
        :current-version="$currentVersion ?? null"
        :version-hrefs="$versionHrefs ?? []"
        :search-placement="$searchPlacement"
        :static-export="$staticExport ?? false"
    >
        <div class="flex min-w-0 min-h-0 flex-1 flex-col">
            <x-vellum::docs.toc :toc="$toc" :document="null" placement="mobile" :sticky-class="$tocSticky" :mobile-sticky-class="$tocMobileSticky" />

            <div data-vellum-page-row class="flex min-w-0 flex-1 gap-8 px-4 py-8 md:px-6 xl:px-8">
            <main id="vellum-content" class="flex min-h-0 w-full min-w-0 max-w-[860px] flex-1 flex-col">
                <x-vellum::docs.breadcrumb :breadcrumbs="$breadcrumbs" :updated-at="$updatedAt ?? null" />

                <article data-vellum-article data-vellum-changelog>
                    <div class="mb-2 flex flex-wrap items-end justify-between gap-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                            {{ $changelog->title }}
                        </h1>
                        <a
                            href="{{ $feedUrl }}"
                            class="text-sm text-muted-foreground hover:text-foreground"
                        >Atom feed</a>
                    </div>
                    <div class="vellum-prose">
                        {!! $changelog->introHtml !!}
                        @foreach ($changelog->visible() as $release)
                            <section @class(['vellum-changelog-release', 'mt-10' => ! $loop->first])>
                                <h2 id="{{ $release->id }}" class="vellum-heading">
                                    @if ($release->url)
                                        <a href="{{ $release->url }}" target="_blank" rel="noopener">{{ $release->version }}</a>
                                    @else
                                        {{ $release->version }}
                                    @endif
                                    @if ($release->date)
                                        <time datetime="{{ $release->date }}" class="ml-2 text-base font-normal text-muted-foreground">{{ $release->date }}</time>
                                    @endif
                                </h2>
                                {!! $release->html !!}
                            </section>
                        @endforeach
                    </div>
                </article>

                <div class="mt-auto">
                    <x-vellum::docs.footer />
                </div>
            </main>

            <x-vellum::docs.toc :toc="$toc" :document="null" placement="desktop" :sticky-class="$tocSticky" />
            </div>
        </div>
    </x-vellum::docs.shell>
@endsection
