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
use Vellum\Search\SearchDriver;

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
        bool $staticExport = false,
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
            'searchDriver' => SearchDriver::name(),
            'staticExport' => $staticExport,
            'versions' => $switcher['versions'],
            'currentVersion' => $switcher['currentVersion'],
            'versionHrefs' => $switcher['versionHrefs'],
            'pageTitle' => self::pageTitle($document->title),
            'canonical' => self::canonical($repository->hrefFor($document->slug, $document->version), $staticExport),
            'markdownSource' => self::source($document),
            'rawUrl' => self::rawUrl($document),
            'editUrl' => self::editUrl($document),
            'updatedAt' => self::updatedAt($document),
            'searchPlacement' => $searchPlacement,
        ];
    }

    /**
     * @return DocsPageData
     */
    public static function changelog(ContentRepository $repository, Changelog $changelog, bool $staticExport = false): array
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
            'searchDriver' => SearchDriver::name(),
            'staticExport' => $staticExport,
            'versions' => $switcher['versions'],
            'currentVersion' => $switcher['currentVersion'],
            'versionHrefs' => $switcher['versionHrefs'],
            'pageTitle' => self::pageTitle($changelog->title),
            'canonical' => self::canonical($repository->hrefFor('changelog', $version), $staticExport),
            'feedUrl' => route('vellum.changelog.atom'),
            'rawUrl' => route('vellum.raw', ['slug' => 'changelog']),
            'updatedAt' => $changelog->mtime > 0
                ? Carbon::createFromTimestamp($changelog->mtime)->toFormattedDateString()
                : null,
            'searchPlacement' => $searchPlacement,
        ];
    }

    /**
     * "<page> · <site>", collapsed to one when the page is the site index and
     * the two would otherwise read "Vellum · Vellum".
     */
    public static function pageTitle(string $title): string
    {
        $name = trim((string) config('vellum.name'));
        $title = trim($title);

        if ($title === '' || $title === $name) {
            return $name === '' ? $title : $name;
        }

        return $name === '' ? $title : $title.' · '.$name;
    }

    /**
     * Absolute URL for the page, or null when app.url is not a real origin, in
     * which case a canonical link would be worse than none.
     */
    public static function canonical(string $path, bool $staticExport = false): ?string
    {
        // An export can be served from somewhere other than the app, so its
        // base_url wins when it names an origin. It defaults to "/", which
        // does not, in which case fall back to the app.
        $base = rtrim((string) config('app.url', ''), '/');

        if ($staticExport) {
            $exportBase = rtrim((string) config('vellum.export.base_url', ''), '/');

            if (self::isOrigin($exportBase)) {
                $base = $exportBase;
            }
        }

        if (! self::isOrigin($base)) {
            return null;
        }

        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }

    private static function isOrigin(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    public static function rawUrl(Document $document): string
    {
        return route('vellum.raw', ['slug' => self::rawSlug($document)]);
    }

    public static function rawSlug(Document $document): string
    {
        $slug = $document->slug === '' ? 'index' : $document->slug;
        $version = $document->version;

        if (
            (bool) config('vellum.versions.enabled')
            && is_string($version)
            && $version !== ''
            && $version !== config('vellum.versions.latest')
        ) {
            return $version.'/'.$slug;
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

    /**
     * The date shown under a page: frontmatter updated, else git, else none.
     * Never the file mtime, which a checkout or composer install resets.
     */
    public static function updatedAt(Document $document): ?string
    {
        if ($document->updated === null || $document->updated === '') {
            return null;
        }

        try {
            return Carbon::parse($document->updated)->toFormattedDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
