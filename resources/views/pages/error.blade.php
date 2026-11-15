@extends('vellum::layouts.docs')

@section('title', __('vellum::errors.not_rendered.title').' · '.config('vellum.name'))

@section('content')
    {{--
        Deliberately not the docs shell: building the navigation recompiles
        content, which is what failed in the first place.
    --}}
    <main id="vellum-content" class="mx-auto flex min-h-screen w-full max-w-2xl flex-col items-start justify-center px-6 py-16">
        <p class="text-sm font-medium text-muted-foreground">500</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ __('vellum::errors.not_rendered.title') }}</h1>
        <p class="mt-3 text-muted-foreground">
            {{ __('vellum::errors.not_rendered.text') }}
        </p>

        @if (! empty($detail))
            <div class="mt-8 w-full rounded-lg border border-border bg-muted/40 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ __('vellum::errors.not_rendered.detail') }}</p>
                <p class="mt-2 font-mono text-sm break-words text-foreground">{{ $detail }}</p>
                @if (! empty($file))
                    <p class="mt-2 font-mono text-xs break-all text-muted-foreground">{{ $file }}</p>
                @endif
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                {!! __('vellum::errors.not_rendered.debug', ['setting' => '<code>APP_DEBUG</code>']) !!}
            </p>
        @endif

        <a
            href="{{ route('vellum.docs.index') }}"
            data-vellum-button
            class="mt-8 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        >
            {{ __('vellum::errors.back') }}
        </a>
    </main>
@endsection
