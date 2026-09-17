<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Changelog\Changelog;

/**
 * Finds links and images that point at nothing, at build time.
 *
 * Both failures are silent at runtime, which is why they need catching here.
 * A link to a page that was renamed still renders as a link and only fails
 * when a reader clicks it. A missing image is worse: the renderer falls back
 * to the URL it was given, so the page compiles, the build passes, and the
 * only symptom is a broken image on a page nobody reloaded.
 *
 * Findings carry a severity. A broken link is an error: something a reader
 * will hit. A heading that skips a level is a notice: worth fixing, not worth
 * failing a deploy over, so --strict leaves it alone.
 *
 * @phpstan-type Finding array{page: string, kind: string, target: string, message: string, severity: string}
 */
final class LinkChecker
{
    /**
     * Routes the package serves that are not compiled documents.
     */
    private const SKIPPED_PREFIXES = ['_vellum/'];

    /**
     * @param  list<Document>  $documents
     * @return list<Finding>
     */
    public function check(array $documents, ContentRepository $repository): array
    {
        $known = $this->knownPages($documents, $repository);
        $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');
        $findings = [];

        foreach ($documents as $document) {
            $here = $repository->hrefFor($document->slug, $document->version);
            $anchors = array_column($document->headings, 'id');

            foreach ($this->hrefs($document->html) as $href) {
                $finding = $this->checkHref($href, $here, $anchors, $known, $prefix);

                if ($finding !== null) {
                    $findings[] = ['page' => $here] + $finding;
                }
            }

            foreach ($this->headingSkips($document) as $finding) {
                $findings[] = ['page' => $here] + $finding;
            }
        }

        foreach ($repository->imageReport()->all() as $missing) {
            $findings[] = [
                'page' => $repository->hrefFor($missing['page'], $missing['version']),
                'kind' => 'image',
                'target' => $missing['url'],
                'message' => 'the renderer found no file at '.$missing['path'],
                'severity' => 'error',
            ];
        }

        return $findings;
    }

    /**
     * Headings that jump a level, which leaves a gap in the outline a screen
     * reader navigates by. The page title is the h1, so the body starts at 2.
     *
     * @return list<array{kind: string, target: string, message: string, severity: string}>
     */
    private function headingSkips(Document $document): array
    {
        $previous = 1;
        $findings = [];

        foreach ($document->headings as $heading) {
            $level = $heading['level'];

            if ($level > $previous + 1) {
                $findings[] = [
                    'kind' => 'heading',
                    'target' => '#'.$heading['id'],
                    'message' => sprintf('h%d follows h%d, so the outline skips a level', $level, $previous),
                    'severity' => 'notice',
                ];
            }

            $previous = $level;
        }

        return $findings;
    }

    /**
     * @param  list<Document>  $documents
     * @return array<string, list<string>> href => heading ids on that page
     */
    private function knownPages(array $documents, ContentRepository $repository): array
    {
        $known = [];

        foreach ($documents as $document) {
            $known[$repository->hrefFor($document->slug, $document->version)] = array_values(array_filter(
                array_column($document->headings, 'id'),
                is_string(...),
            ));
        }

        // The changelog is a route rather than a file, so nothing above lists it.
        $changelog = Changelog::load();

        if ($changelog !== null) {
            $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');
            $known['/'.$prefix.'/changelog'] = array_values(array_filter(
                array_column($changelog->visibleHeadings(), 'id'),
                is_string(...),
            ));
        }

        return $known;
    }

    /**
     * @param  list<string>  $anchors
     * @param  array<string, list<string>>  $known
     * @return array{kind: string, target: string, message: string, severity: string}|null
     */
    private function checkHref(string $href, string $here, array $anchors, array $known, string $prefix): ?array
    {
        $href = trim($href);

        if ($href === '' || $this->isExternal($href)) {
            return null;
        }

        if (str_starts_with($href, '#')) {
            $fragment = substr($href, 1);

            return $fragment === '' || in_array($fragment, $anchors, true)
                ? null
                : ['kind' => 'anchor', 'target' => $href, 'message' => 'no heading with this id on the page', 'severity' => 'error'];
        }

        [$path, $fragment] = $this->split($href);
        $path = str_starts_with($path, '/') ? $path : $this->resolveRelative($path, $here);
        $path = rtrim($path, '/');
        $path = $path === '' ? '/' : $path;

        foreach (self::SKIPPED_PREFIXES as $skip) {
            if (str_starts_with(ltrim($path, '/'), $prefix.'/'.$skip)) {
                return null;
            }
        }

        if (! array_key_exists($path, $known)) {
            return ['kind' => 'link', 'target' => $href, 'message' => 'no page at this path', 'severity' => 'error'];
        }

        if ($fragment !== null && $fragment !== '' && ! in_array($fragment, $known[$path], true)) {
            return ['kind' => 'anchor', 'target' => $href, 'message' => 'the page exists, but has no heading with this id', 'severity' => 'error'];
        }

        return null;
    }

    private function isExternal(string $url): bool
    {
        return preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url) === 1;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function split(string $href): array
    {
        $href = explode('?', $href, 2)[0];
        $parts = explode('#', $href, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private function resolveRelative(string $path, string $here): string
    {
        // Docs URLs carry no trailing slash, so a browser reads the last
        // segment as the page and resolves siblings against its parent.
        $base = rtrim(dirname($here), '/');
        $segments = [];

        foreach (explode('/', $base.'/'.$path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }

    /**
     * @return list<string>
     */
    private function hrefs(string $html): array
    {
        preg_match_all('~<a\s[^>]*href="([^"]*)"~i', $html, $matches);

        return array_map(html_entity_decode(...), $matches[1]);
    }
}
