@extends('vellum::layouts.docs')

@section('title', $pageTitle ?? __('vellum::errors.not_found.title'))

@section('content')
    <x-vellum::docs.shell
        :document="null"
        :navigation="$navigation ?? []"
        :sections="$sections ?? []"
        :versions="$versions ?? []"
        :current-version="$currentVersion ?? null"
        :version-hrefs="$versionHrefs ?? []"
        :version-pages="$versionPages ?? null"
        :search-placement="$searchPlacement ?? 'sidebar'"
        :static-export="$staticExport ?? false"
    >
        <main id="vellum-content" data-vellum-404 data-vellum-page-row class="flex min-w-0 flex-1 flex-col items-start justify-center px-4 py-16 md:px-6 xl:px-8">
            <p class="text-sm font-medium text-muted-foreground">404</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ __('vellum::errors.not_found.title') }}</h1>
            <p class="mt-3 max-w-md text-muted-foreground">
                {{ __('vellum::errors.not_found.text') }}
            </p>
            <a
                href="{{ route('vellum.docs.index') }}"
                data-vellum-button
                class="mt-8 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                {{ __('vellum::errors.back') }}
            </a>
        </main>
    </x-vellum::docs.shell>
@endsection
