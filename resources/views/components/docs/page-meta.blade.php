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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5" aria-hidden="true">
                <path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/>
            </svg>
            Edit on GitHub
        </a>
    @endif
</div>
