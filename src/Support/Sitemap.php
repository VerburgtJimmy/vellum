<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Content\Access;
use Vellum\Content\ContentRepository;
use Vellum\Http\DocsView;

/**
 * Builds the XML sitemap for the docs, served and exported from one place.
 *
 * Vellum already knows every page, its URL and whether it is gated, so the
 * sitemap is derived from the same navigation the sidebar renders rather than
 * being something each project has to write for itself.
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
        $builder = $repository->navigationBuilder();
        $versions = $repository->versionsEnabled() ? $repository->versions() : [null];
        $urls = [];

        foreach ($versions as $version) {
            foreach ($builder->flattenPages($repository->navigation($version)) as $page) {
                // navigation() is filtered for whoever is asking. A sitemap is
                // the same file for everyone, so list guest pages only.
                if (Access::normalize($page['access']) !== 'guest' || ($page['requires'] ?? []) !== []) {
                    continue;
                }

                // An external meta.json entry is a link out, not a page of these docs.
                if (preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $page['href']) === 1) {
                    continue;
                }

                $url = DocsView::canonical($page['href'], $staticExport);

                if ($url === null) {
                    return [];
                }

                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * @param  list<string>  $urls
     */
    public static function render(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $lines[] = '    <url>';
            $lines[] = '        <loc>'.htmlspecialchars($url, ENT_XML1).'</loc>';
            $lines[] = '    </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
