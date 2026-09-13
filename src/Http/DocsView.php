<?php

declare(strict_types=1);

namespace Vellum\Http;

use Illuminate\Support\Carbon;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\HeadingExtractor;

/**
 * Shared view data for live docs pages and static export.
 *
 * @phpstan-type DocsPageData array<string, mixed>
 */
final class DocsView
{
    /**
     * @return DocsPageData
     */
    public static function document(
        ContentRepository $repository,
        Document $document,
        HeadingExtractor $headingExtractor,
    ): array {
        $adjacent = $repository->adjacent($document->slug, $document->version);
        $switcher = $repository->versionSwitcherData($document->slug, $document->version);
        $searchPlacement = config('vellum.layout.search', 'sidebar') === 'header' ? 'header' : 'sidebar';

        return [
            'document' => $document,
            'name' => config('vellum.name'),
            'description' => $document->description,
            'navigation' => $repository->navigation($document->version),
            'previous' => $adjacent['previous'],
            'next' => $adjacent['next'],
            'breadcrumbs' => $repository->breadcrumbs($document),
            'toc' => $headingExtractor->nest($document->headings),
            'searchHash' => $repository->searchHash($document->version),
            'versions' => $switcher['versions'],
            'currentVersion' => $switcher['currentVersion'],
            'versionHrefs' => $switcher['versionHrefs'],
            'markdownSource' => self::source($document),
            'rawUrl' => route('vellum.raw', ['slug' => self::rawSlug($document)]),
            'editUrl' => self::editUrl($document),
            'updatedAt' => self::updatedAt($document),
            'searchPlacement' => $searchPlacement,
        ];
    }

    public static function rawSlug(Document $document): string
    {
        $slug = $document->slug === '' ? 'index' : $document->slug;

        if (is_string($document->version) && $document->version !== '') {
            return $document->version.'/'.$slug;
        }

        return $slug;
    }

    public static function source(Document $document): string
    {
        if (! is_file($document->path)) {
            return '';
        }

        $contents = file_get_contents($document->path);

        return $contents === false ? '' : $contents;
    }

    public static function editUrl(Document $document): ?string
    {
        $repo = config('vellum.repo');

        if (! is_string($repo) || $repo === '') {
            return null;
        }

        $contentPath = realpath((string) config('vellum.path'));
        $docPath = realpath($document->path);

        if ($contentPath === false || $docPath === false || ! str_starts_with($docPath, $contentPath)) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', substr($docPath, strlen($contentPath))), '/');

        return rtrim($repo, '/').'/'.$relative;
    }

    public static function updatedAt(Document $document): ?string
    {
        if ($document->mtime <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($document->mtime)->toFormattedDateString();
    }
}
