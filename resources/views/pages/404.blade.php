@extends('vellum::layouts.docs')

@section('title', 'Page not found · '.($name ?? config('vellum.name')))

@section('content')
    <div data-vellum-docs data-vellum-404 class="flex min-h-screen flex-col">
        <x-vellum::docs.header
            :document="null"
            :navigation="$navigation ?? []"
            :search-hash="$searchHash ?? null"
            :versions="$versions ?? []"
            :current-version="$currentVersion ?? null"
            :version-hrefs="$versionHrefs ?? []"
        />

        <div class="mx-auto flex w-full max-w-[1400px] flex-1">
            <div class="hidden md:block">
                <div class="sticky top-14 h-[calc(100vh-3.5rem)]">
                    <x-vellum::docs.sidebar :navigation="$navigation ?? []" :document="null" />
                </div>
            </div>

            <main id="vellum-content" class="flex min-w-0 flex-1 flex-col items-start justify-center px-4 py-16 md:px-8">
                <p class="text-sm font-medium text-muted-foreground">404</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Page not found</h1>
                <p class="mt-3 max-w-md text-muted-foreground">
                    The page you are looking for does not exist or may have been moved.
                </p>
                <a
                    href="{{ route('vellum.docs.index') }}"
                    data-vellum-button
                    class="mt-8 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Back to docs
                </a>
            </main>
        </div>
    </div>
@endsection
