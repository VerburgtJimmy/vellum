<?php

declare(strict_types=1);

namespace Vellum\Http;

use Illuminate\Support\Carbon;
use Vellum\Cache\FragmentCache;
use Vellum\Changelog\Changelog;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\HeadingExtractor;
use Vellum\Markdown\Islands\IslandRenderer;

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
        bool $cacheFragment = true,
    ): array {
        $adjacent = $repository->adjacent($document->slug, $document->version);
        $switcher = $repository->versionSwitcherData($document->slug, $document->version);
        $searchPlacement = config('vellum.layout.search', 'sidebar') === 'header' ? 'header' : 'sidebar';
        $render = static fn (): string => (new IslandRenderer)->render($document->html, $document->islands);
        $html = $cacheFragment
            ? (new FragmentCache)->remember($document, $render)
            : $render();

        return [
            'document' => $document,
            'html' => $html,
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

    /**
     * @return DocsPageData
     */
    public static function changelog(ContentRepository $repository, Changelog $changelog): array
    {
        $version = $repository->latestVersion();
        $switcher = $repository->versionSwitcherData('', $version);
        $searchPlacement = config('vellum.layout.search', 'sidebar') === 'header' ? 'header' : 'sidebar';

        $headings = $changelog->visibleHeadings();

        $document = new Document(
            slug: 'changelog',
            title: $changelog->title,
            html: '',
            headings: $headings,
            frontmatter: [],
            path: $changelog->path,
            mtime: $changelog->mtime,
            description: 'Release notes',
        );

        return [
            'document' => $document,
            'changelog' => $changelog,
            'name' => config('vellum.name'),
            'description' => 'Release notes',
            'navigation' => $repository->navigation($version),
            'previous' => null,
            'next' => null,
            'breadcrumbs' => [
                ['title' => $changelog->title, 'href' => null, 'slug' => 'changelog'],
            ],
            'toc' => (new HeadingExtractor)->nest($headings),
            'searchHash' => $repository->searchHash($version),
            'versions' => $switcher['versions'],
            'currentVersion' => $switcher['currentVersion'],
            'versionHrefs' => $switcher['versionHrefs'],
            'feedUrl' => route('vellum.changelog.atom'),
            'updatedAt' => $changelog->mtime > 0
                ? Carbon::createFromTimestamp($changelog->mtime)->toFormattedDateString()
                : null,
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
