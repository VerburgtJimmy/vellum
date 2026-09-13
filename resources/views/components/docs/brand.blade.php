@php
    $name = config('vellum.name', 'Docs');
    $logo = config('vellum.logo');
    $homeHref = route('vellum.docs.index');
@endphp

<a href="{{ $homeHref }}" data-vellum-brand class="flex min-w-0 items-center gap-2 font-semibold tracking-tight text-foreground hover:opacity-90">
    @if (is_string($logo) && $logo !== '')
        @if (str_ends_with($logo, '.svg') && is_file($logo))
            <span class="flex h-6 w-6 shrink-0 items-center justify-center [&>svg]:h-6 [&>svg]:w-6" aria-hidden="true">
                {!! file_get_contents($logo) !!}
            </span>
        @elseif (view()->exists($logo))
            <span class="flex h-6 w-6 shrink-0 items-center justify-center" aria-hidden="true">
                @include($logo)
            </span>
        @endif
    @endif
    <span class="truncate">{{ $name }}</span>
</a>
