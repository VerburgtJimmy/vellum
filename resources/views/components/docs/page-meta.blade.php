@props([
    'document',
])

@php
    $updated = $document->mtime > 0
        ? \Illuminate\Support\Carbon::createFromTimestamp($document->mtime)->toFormattedDateString()
        : null;

    $repo = config('vellum.repo');
    $editUrl = null;

    if (is_string($repo) && $repo !== '') {
        $contentPath = realpath((string) config('vellum.path'));
        $docPath = realpath($document->path);

        if ($contentPath && $docPath && str_starts_with($docPath, $contentPath)) {
            $relative = ltrim(str_replace('\\', '/', substr($docPath, strlen($contentPath))), '/');
            $editUrl = rtrim($repo, '/').'/'.$relative;
        }
    }
@endphp

<div data-vellum-page-meta class="mt-10 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-border pt-6 text-sm text-muted-foreground">
    @if ($updated)
        <span>Last updated {{ $updated }}</span>
    @endif

    @if ($editUrl)
        <a
            href="{{ $editUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 hover:text-foreground"
        >
            {!! \Vellum\Support\Icons::pencilSimple(['class' => 'h-3.5 w-3.5']) !!}
            Edit on GitHub
        </a>
    @endif
</div>
