<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Content\Access;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Http\DocsView;

/**
 * Builds llms.txt and llms-full.txt for the docs, served and exported from one place.
 *
 * Like the sitemap, both are derived from the navigation that renders the
 * sidebar, list guest pages only, and cover the latest version only: an agent
 * wants the current docs, not every version of them.
 *
 * @phpstan-type Entry array{title: string, description: string|null, href: string, document: Document}
 * @phpstan-type Section array{title: string, entries: list<Entry>}
 */
final class LlmsTxt
{
    /**
     * Heading for top-level pages that sit outside any folder or separator.
     */
    public const DEFAULT_SECTION = 'Docs';

    /**
     * Opens and closes the header in front of each page in llms-full.txt.
     */
    public const SEPARATOR = '================================================================================';

    public static function enabled(): bool
    {
        return (bool) config('vellum.agents.llms_txt', true);
    }

    /**
     * The index: one line per page, linking to its raw Markdown.
     */
    public static function index(ContentRepository $repository, bool $staticExport = false): string
    {
        $lines = self::header($repository);

        foreach (self::sections($repository) as $section) {
            $lines[] = '## '.$section['title'];
            $lines[] = '';

            foreach ($section['entries'] as $entry) {
                $line = '- ['.self::linkText($entry['title']).']('.self::url(self::rawPath($entry['document']), $staticExport).')';
                $description = self::oneLine($entry['description'] ?? '');

                $lines[] = $description === '' ? $line : $line.': '.$description;
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * Every page's Markdown source in sidebar order, each behind a header
     * naming its title, its URL and, when versions are on, its version.
     */
    public static function full(ContentRepository $repository, bool $staticExport = false): string
    {
        $lines = self::header($repository);
        $version = $repository->versionsEnabled() ? $repository->latestVersion() : null;

        foreach (self::sections($repository) as $section) {
            foreach ($section['entries'] as $entry) {
                $lines[] = self::SEPARATOR;
                $lines[] = 'Title: '.self::oneLine($entry['title']);
                $lines[] = 'URL: '.self::url($entry['href'], $staticExport);

                if ($version !== null) {
                    $lines[] = 'Version: '.$version;
                }

                $lines[] = self::SEPARATOR;
                $lines[] = '';
                $lines[] = rtrim(DocsView::source($entry['document']));
                $lines[] = '';
            }
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * @return list<string>
     */
    private static function header(ContentRepository $repository): array
    {
        $lines = ['# '.self::oneLine((string) config('vellum.name')), ''];
        $index = $repository->find('', $repository->latestVersion());
        $summary = $index !== null && Access::normalize($index->access()) === 'guest'
            ? self::oneLine($index->description ?? '')
            : '';

        if ($summary !== '') {
            $lines[] = '> '.$summary;
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * Group the sidebar into sections.
     *
     * Each top-level folder is a section, and so is the run of top-level pages
     * under each titled separator. Top-level pages before any separator go
     * first, under DEFAULT_SECTION. Anything nested inside a folder belongs to
     * that folder's section, since llms.txt has no deeper level than ##.
     *
     * @return list<Section>
     */
    private static function sections(ContentRepository $repository): array
    {
        $version = $repository->latestVersion();
        $builder = $repository->navigationBuilder();
        $titles = ['default' => self::DEFAULT_SECTION];
        /** @var array<string, list<Entry>> $entries */
        $entries = [];
        $current = 'default';
        $seen = [];

        foreach ($repository->navigation($version) as $i => $node) {
            $type = $node['type'] ?? null;
            $title = is_string($node['title'] ?? null) ? trim($node['title']) : '';

            if ($type === 'separator') {
                // An untitled separator is a divider line, not a new group.
                if ($title !== '') {
                    $current = 'separator-'.$i;
                    $titles[$current] = $title;
                }

                continue;
            }

            $key = $current;
            $pages = [];

            if ($type === 'folder' && is_array($node['children'] ?? null)) {
                $key = 'folder-'.$i;
                $titles[$key] = $title === '' ? self::DEFAULT_SECTION : $title;
                $pages = $builder->flattenPages(array_values($node['children']));
            } elseif ($type === 'page') {
                $pages = $builder->flattenPages([$node]);
            }

            foreach ($pages as $page) {
                $entry = self::entry($repository, $page, $version, $seen);

                if ($entry !== null) {
                    $entries[$key][] = $entry;
                }
            }
        }

        $sections = [];

        foreach ($titles as $key => $title) {
            if (($entries[$key] ?? []) !== []) {
                $sections[] = ['title' => $title, 'entries' => $entries[$key]];
            }
        }

        return $sections;
    }

    /**
     * A nav page that is a real document a guest may read, or null.
     *
     * Links added in meta.json have no Markdown source, so they are skipped.
     *
     * @param  array{slug: string, title: string, description: string|null, icon: string|null, href: string, access: string}  $page
     * @param  array<string, true>  $seen
     * @return Entry|null
     */
    private static function entry(ContentRepository $repository, array $page, ?string $version, array &$seen): ?array
    {
        // navigation() is filtered for whoever is asking. These files are the
        // same for everyone, so list guest pages only.
        if (Access::normalize($page['access']) !== 'guest' || isset($seen[$page['slug']])) {
            return null;
        }

        $document = $repository->find($page['slug'], $version);

        if ($document === null || Access::normalize($document->access()) !== 'guest' || ! is_file($document->path)) {
            return null;
        }

        $seen[$page['slug']] = true;

        return [
            'title' => $page['title'],
            'description' => $page['description'],
            'href' => $page['href'],
            'document' => $document,
        ];
    }

    private static function rawPath(Document $document): string
    {
        return route('vellum.raw', ['slug' => DocsView::rawSlug($document)], false);
    }

    /**
     * Absolute when an origin is configured, the same rule as the canonical
     * link. Root-relative otherwise: unlike a sitemap, llms.txt is Markdown and
     * a relative link in it is still a link.
     */
    private static function url(string $path, bool $staticExport): string
    {
        $url = DocsView::canonical($path, $staticExport);

        if ($url !== null) {
            return $url;
        }

        $path = '/'.ltrim($path, '/');
        $base = trim((string) config('vellum.export.base_url', '/'), '/');

        // An export served from a subdirectory needs that directory in front.
        if ($staticExport && $base !== '' && ! str_contains($base, '://')) {
            return '/'.$base.$path;
        }

        return $path;
    }

    private static function linkText(string $title): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], self::oneLine($title));
    }

    private static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
