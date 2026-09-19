<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Changelog\Changelog;
use Vellum\Content\Access;
use Vellum\Content\ContentRepository;
use Vellum\Http\DocsView;

/**
 * Builds the XML sitemap for the docs, served and exported from one place.
 *
 * Vellum already knows every page, its URL and whether it is gated, so the
 * sitemap is derived from the same navigation the sidebar renders rather than
 * being something each project has to write for itself.
 *
 * @phpstan-type SitemapEntry array{loc: string, lastmod: string|null}
 */
final class Sitemap
{
    /**
     * Absolute URLs for every publicly readable page, in sidebar order.
     *
     * Empty when no origin is configured: sitemap URLs have to be absolute,
     * so a relative one would be worse than omitting the file. This is the
     * same rule DocsView applies to the canonical link.
     *
     * @return list<string>
     */
    public static function urls(ContentRepository $repository, bool $staticExport = false): array
    {
        return array_column(self::entries($repository, $staticExport), 'loc');
    }

    /**
     * The same pages as urls(), each with its last-updated date when it has
     * one. A page without a date gets no <lastmod> rather than a guess.
     *
     * @return list<SitemapEntry>
     */
    public static function entries(ContentRepository $repository, bool $staticExport = false): array
    {
        $builder = $repository->navigationBuilder();
        $versions = $repository->versionsEnabled() ? $repository->versions() : [null];
        $entries = [];

        foreach ($versions as $version) {
            foreach ($builder->flattenPages($repository->navigation($version)) as $page) {
                // navigation() is filtered for whoever is asking. A sitemap is
                // the same file for everyone, so list guest pages only.
                if (Access::normalize($page['access']) !== 'guest') {
                    continue;
                }

                $url = DocsView::canonical($page['href'], $staticExport);

                if ($url === null) {
                    return [];
                }

                // A meta.json link can carry any href, so only a node that
                // points at its own slug's page is looked up for a date.
                $lastmod = null;

                if ($page['href'] === $repository->hrefFor($page['slug'], $version)) {
                    $changelog = $page['slug'] === 'changelog' ? Changelog::load() : null;
                    $lastmod = $changelog !== null
                        ? $changelog->updated()
                        : $repository->find($page['slug'], $version)?->updated;
                }

                $entries[$url] ??= ['loc' => $url, 'lastmod' => $lastmod];
            }
        }

        return array_values($entries);
    }

    /**
     * @param  list<string|SitemapEntry>  $entries
     */
    public static function render(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $entry = is_string($entry) ? ['loc' => $entry, 'lastmod' => null] : $entry;

            $lines[] = '    <url>';
            $lines[] = '        <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';

            if ($entry['lastmod'] !== null) {
                $lines[] = '        <lastmod>'.htmlspecialchars($entry['lastmod'], ENT_XML1).'</lastmod>';
            }

            $lines[] = '    </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
