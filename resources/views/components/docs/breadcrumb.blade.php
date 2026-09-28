@props([
    'breadcrumbs' => [],
])

@if ($breadcrumbs !== [])
    <nav data-vellum-breadcrumb aria-label="Breadcrumb" class="mb-4 min-w-0">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
            @foreach ($breadcrumbs as $index => $crumb)
                <li class="inline-flex items-center gap-1.5">
                    @if ($index > 0)
                        {!! \Vellum\Support\Icons::caretRight(['class' => 'h-3.5 w-3.5 shrink-0 opacity-60']) !!}
                    @endif
                    @if (! empty($crumb['href']) && ! $loop->last)
                        <a href="{{ $crumb['href'] }}" class="hover:text-foreground">{{ $crumb['title'] }}</a>
                    @else
                        <span @if ($loop->last) class="text-foreground" aria-current="page" @endif>{{ $crumb['title'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
