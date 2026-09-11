@extends('vellum::layouts.docs')

@section('title', $document->title.' · '.($name ?? config('vellum.name')))

@section('content')
    <div data-vellum-docs class="flex min-h-screen flex-col">
        <x-vellum::docs.header
            :document="$document"
            :navigation="$navigation"
            :search-hash="$searchHash"
            :versions="$versions ?? []"
            :current-version="$currentVersion ?? null"
            :version-hrefs="$versionHrefs ?? []"
        />

        <div class="mx-auto flex w-full max-w-[1400px] flex-1">
            <div class="hidden md:block">
                <div class="sticky top-14 h-[calc(100vh-3.5rem)]">
                    <x-vellum::docs.sidebar :navigation="$navigation" :document="$document" />
                </div>
            </div>

            <div class="flex min-w-0 flex-1 justify-center gap-8 px-4 py-8 md:px-8">
                <main id="vellum-content" class="min-w-0 w-full max-w-[860px]">
                    <x-vellum::docs.toc :toc="$toc" :document="$document" placement="mobile" />

                    <x-vellum::docs.breadcrumb :breadcrumbs="$breadcrumbs" />

                    <article data-vellum-article>
                        <h1 class="mb-2 text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                            {{ $document->title }}
                        </h1>
                        @if ($document->description)
                            <p class="mb-8 text-lg text-muted-foreground">{{ $document->description }}</p>
                        @endif
                        <div class="vellum-prose">
                            {!! $document->html !!}
                        </div>
                    </article>

                    <x-vellum::docs.page-meta :document="$document" />
                    <x-vellum::docs.pagination :previous="$previous" :next="$next" />
                    <x-vellum::docs.footer />
                </main>

                <x-vellum::docs.toc :toc="$toc" :document="$document" placement="desktop" />
            </div>
        </div>
    </div>
@endsection
