<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Changelog\Changelog;
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
 * @phpstan-type Entry array{title: string, description: string|null, href: string, raw: string, source: \Closure(): string, updated: string|null, versioned: bool}
 * @phpstan-type Section array{title: string, entries: list<Entry>}
 */
final class LlmsTxt
{
    /**
     * Heading for the first run of top-level pages when the content root's
     * meta.json has no title.
     */
    public const DEFAULT_SECTION = 'Docs';

    /**
     * Opens and closes the header in front of each page in llms-full.txt.
     */
    public const SEPARATOR = '================================================================================';

    /**
     * Where the changelog goes when the sidebar does not list it. The
     * llms.txt format reserves this heading for links an agent may skip
     * when it is short on context, which is what release notes are.
     */
    public const OPTIONAL_SECTION = 'Optional';

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
                $line = '- ['.self::linkText($entry['title']).']('.self::url($entry['raw'], $staticExport).')';
                $description = self::oneLine($entry['description'] ?? '');

                $lines[] = $description === '' ? $line : $line.': '.$description;
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * Every page's Markdown source in sidebar order, each behind a header
     * naming its title, its URL and, when known, its version and the date it
     * was last updated.
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

                if ($version !== null && $entry['versioned']) {
                    $lines[] = 'Version: '.$version;
                }

                if ($entry['updated'] !== null) {
                    $lines[] = 'Updated: '.$entry['updated'];
                }

                $lines[] = self::SEPARATOR;
                $lines[] = '';
                $lines[] = rtrim(($entry['source'])());
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
     * Group the sidebar into sections, in sidebar order.
     *
     * Each top-level folder is a section, with anything nested inside it
     * flattened into it, since llms.txt has no level below ##. A run of
     * consecutive top-level pages is a section too, headed by the titled
     * separator above it, or by the root title before any separator.
     *
     * A run that resumes after a folder gets the same heading again, marked
     * "(continued)". Headings stay unique because llms.txt tools commonly read
     * the sections into a map keyed by heading, where a repeat would replace
     * the earlier section rather than add to it.
     *
     * @return list<Section>
     */
    private static function sections(ContentRepository $repository): array
    {
        $version = $repository->latestVersion();
        $builder = $repository->navigationBuilder();
        $heading = self::rootTitle($repository, $version);
        /** @var list<Section> $sections */
        $sections = [];
        /** @var list<Entry> $run */
        $run = [];
        $seen = [];

        foreach ($repository->navigation($version) as $node) {
            $type = $node['type'] ?? null;
            $title = is_string($node['title'] ?? null) ? trim($node['title']) : '';

            if ($type === 'separator') {
                // An untitled separator is a divider line, not a new group.
                if ($title !== '') {
                    $sections = self::close($sections, $heading, $run);
                    $run = [];
                    $heading = $title;
                }

                continue;
            }

            if ($type === 'folder' && is_array($node['children'] ?? null)) {
                $entries = [];

                foreach ($builder->flattenPages(array_values($node['children'])) as $page) {
                    $entry = self::entry($repository, $page, $version, $seen);

                    if ($entry !== null) {
                        $entries[] = $entry;
                    }
                }

                // A folder with nothing public in it does not split the run.
                if ($entries !== []) {
                    $sections = self::close($sections, $heading, $run);
                    $run = [];
                    $sections[] = ['title' => $title === '' ? $heading : $title, 'entries' => $entries];
                }

                continue;
            }

            if ($type !== 'page') {
                continue;
            }

            foreach ($builder->flattenPages([$node]) as $page) {
                $entry = self::entry($repository, $page, $version, $seen);

                if ($entry !== null) {
                    $run[] = $entry;
                }
            }
        }

        $sections = self::close($sections, $heading, $run);
        $changelog = Changelog::load();

        if ($changelog !== null && ! isset($seen['changelog'])) {
            $sections[] = [
                'title' => self::OPTIONAL_SECTION,
                'entries' => [self::changelogEntry($repository, $changelog, null)],
            ];
        }

        return self::uniqueTitles($sections);
    }

    /**
     * @param  list<Section>  $sections
     * @param  list<Entry>  $run
     * @return list<Section>
     */
    private static function close(array $sections, string $heading, array $run): array
    {
        if ($run !== []) {
            $sections[] = ['title' => $heading, 'entries' => $run];
        }

        return $sections;
    }

    /**
     * The content root's meta.json title, which is what the sidebar is a
     * tree of, or DEFAULT_SECTION when it has none.
     */
    private static function rootTitle(ContentRepository $repository, ?string $version): string
    {
        $root = rtrim((string) config('vellum.path'), DIRECTORY_SEPARATOR);

        if ($repository->versionsEnabled() && $version !== null && $version !== '') {
            $root .= DIRECTORY_SEPARATOR.$version;
        }

        $path = $root.DIRECTORY_SEPARATOR.'meta.json';
        $json = is_file($path) ? file_get_contents($path) : false;
        $meta = is_string($json) ? json_decode($json, true) : null;
        $title = is_array($meta) && is_string($meta['title'] ?? null) ? self::oneLine($meta['title']) : '';

        return $title === '' ? self::DEFAULT_SECTION : $title;
    }

    /**
     * @param  list<Section>  $sections
     * @return list<Section>
     */
    private static function uniqueTitles(array $sections): array
    {
        $used = [];

        foreach ($sections as $i => $section) {
            $title = $section['title'];
            $candidate = $title;
            $n = 1;

            while (isset($used[strtolower($candidate)])) {
                $candidate = $n === 1 ? $title.' (continued)' : $title.' (continued '.$n.')';
                $n++;
            }

            $used[strtolower($candidate)] = true;
            $sections[$i]['title'] = $candidate;
        }

        return $sections;
    }

    /**
     * A nav page that is a real document a guest may read, or null.
     *
     * Links added in meta.json have no Markdown source, so they are skipped,
     * except the changelog, which is served from its own file.
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

        // A link's href can point anywhere. Only a node that goes to its own
        // slug's page stands for that page.
        if ($page['href'] !== $repository->hrefFor($page['slug'], $version)) {
            return null;
        }

        if ($page['slug'] === 'changelog' && ($changelog = Changelog::load()) !== null) {
            $seen['changelog'] = true;

            return self::changelogEntry($repository, $changelog, $page['description']);
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
            'raw' => self::rawPath($document),
            'source' => static fn (): string => DocsView::source($document),
            'updated' => $document->updated,
            'versioned' => true,
        ];
    }

    /**
     * The changelog is one file for every version, so its header names no
     * version. Its source is what the raw route serves, which leaves out
     * [Unreleased] whenever the HTML page does.
     *
     * @return Entry
     */
    private static function changelogEntry(ContentRepository $repository, Changelog $changelog, ?string $description): array
    {
        return [
            'title' => $changelog->title,
            'description' => $description ?? 'Release notes',
            'href' => $repository->hrefFor('changelog', $repository->latestVersion()),
            'raw' => route('vellum.raw', ['slug' => 'changelog'], false),
            'source' => static fn (): string => $changelog->rawMarkdown(),
            'updated' => $changelog->updated(),
            'versioned' => false,
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
