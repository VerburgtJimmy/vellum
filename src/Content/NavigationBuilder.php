<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Support\SafeHref;
use Vellum\Support\Slug;
use Vellum\Support\Str;
use Vellum\Support\VersionUrl;

/**
 * Builds the sidebar navigation tree from folders, meta.json, and documents.
 *
 * @phpstan-type NavPage array{type: 'page', slug: string, title: string, description: string|null, icon: string|null, href: string, access: string, requires?: list<string>}
 * @phpstan-type NavSeparator array{type: 'separator', title: string}
 * @phpstan-type NavNode array<string, mixed>
 * @phpstan-type NavTree list<array<string, mixed>>
 * @phpstan-type Breadcrumb array{title: string, href: string|null, slug: string|null}
 * @phpstan-type NavChild array{key: string, node: NavPage|null, folder?: string, isFolder: bool, slug?: string, order: int|null}
 */
final class NavigationBuilder
{
    public function __construct(
        private readonly string $contentPath,
        private readonly string $routePrefix = 'docs',
        private readonly ?string $defaultVersion = null,
    ) {}

    /**
     * Build the sidebar tree for a content root (optionally a version folder).
     *
     * @param  list<Document>  $documents
     * @return NavTree
     */
    public function build(array $documents, ?string $version = null): array
    {
        $root = $this->rootPath($version);
        $bySlug = [];

        foreach ($documents as $document) {
            $bySlug[$document->slug] = $document;
        }

        return $this->buildFolder($root, '', $bySlug, $version);
    }

    /**
     * Flatten page nodes in sidebar order for prev/next resolution.
     *
     * @param  NavTree  $tree
     * @return list<NavPage>
     */
    public function flattenPages(array $tree): array
    {
        $pages = [];

        foreach ($tree as $node) {
            if (($node['type'] ?? null) === 'page') {
                /** @var NavPage $node */
                $pages[] = $node;
            }

            if (($node['type'] ?? null) === 'folder' && isset($node['children']) && is_array($node['children'])) {
                /** @var NavTree $children */
                $children = array_values($node['children']);

                foreach ($this->flattenPages($children) as $child) {
                    $pages[] = $child;
                }
            }
        }

        return $pages;
    }

    /**
     * Resolve previous and next pages for a document slug.
     *
     * @param  NavTree  $tree
     * @return array{previous: NavPage|null, next: NavPage|null}
     */
    public function adjacent(array $tree, string $slug): array
    {
        $pages = $this->flattenPages($tree);
        $index = null;

        foreach ($pages as $i => $page) {
            if ($page['slug'] === $slug) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $pages[$index - 1] ?? null,
            'next' => $pages[$index + 1] ?? null,
        ];
    }

    /**
     * Build breadcrumb crumbs for a document slug.
     *
     * @param  NavTree  $tree
     * @return list<Breadcrumb>
     */
    public function breadcrumbs(array $tree, string $slug, string $title): array
    {
        $crumbs = [
            [
                'title' => 'Docs',
                'href' => $this->hrefForSlug('', null),
                'slug' => '',
            ],
        ];

        $path = $this->findPath($tree, $slug);

        if ($path === null) {
            if ($slug !== '') {
                $crumbs[] = [
                    'title' => $title,
                    'href' => null,
                    'slug' => $slug,
                ];
            }

            return $crumbs;
        }

        foreach ($path as $node) {
            if (($node['type'] ?? null) === 'folder' && isset($node['title']) && is_string($node['title'])) {
                $crumbs[] = [
                    'title' => $node['title'],
                    'href' => null,
                    'slug' => null,
                ];
            }

            if (($node['type'] ?? null) === 'page' && isset($node['title'], $node['slug']) && is_string($node['title']) && is_string($node['slug'])) {
                $crumbs[] = [
                    'title' => $node['title'],
                    'href' => null,
                    'slug' => $node['slug'],
                ];
            }
        }

        return $crumbs;
    }

    /**
     * Cheap hash of the docs directory listing for local nav invalidation.
     * Production never calls it on a request: it serves the sidebar the build
     * wrote.
     */
    public function directoryHash(?string $version = null): string
    {
        $root = $this->rootPath($version);

        if (! is_dir($root)) {
            return hash('xxh128', '');
        }

        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = $this->relativePath($root, $file->getPathname());
            // Size and modification time: an edit that keeps the length, such
            // as order: 2 becoming order: 3, still has to rebuild the sidebar.
            $entries[] = $relative.'|'.($file->isFile() ? $file->getSize().'|'.$file->getMTime() : 'dir');
        }

        sort($entries);

        return hash('xxh128', implode("\n", $entries));
    }

    /**
     * @param  array<string, Document>  $bySlug
     * @return NavTree
     */
    private function buildFolder(string $absoluteFolder, string $slugPrefix, array $bySlug, ?string $version): array
    {
        $meta = $this->readMeta($absoluteFolder);
        $children = $this->discoverChildren($absoluteFolder, $slugPrefix, $bySlug, $version);

        if ($meta !== null && isset($meta['pages']) && is_array($meta['pages'])) {
            /** @var list<mixed> $pages */
            $pages = array_values($meta['pages']);

            return $this->orderChildren($absoluteFolder, $children, $pages, $bySlug, $version);
        }

        return $this->defaultOrder($children, $bySlug, $version);
    }

    /**
     * @param  array<string, NavChild>  $children
     * @param  list<mixed>  $pages
     * @param  array<string, Document>  $bySlug
     * @return NavTree
     */
    private function orderChildren(
        string $absoluteFolder,
        array $children,
        array $pages,
        array $bySlug,
        ?string $version,
    ): array {
        $ordered = [];
        $used = [];
        $explicitLater = [];

        foreach ($pages as $entry) {
            if (! is_string($entry) || $entry === '...' || preg_match('/^---(.*?)---$/', $entry) === 1) {
                continue;
            }

            $explicitLater[$entry] = true;
        }

        foreach ($pages as $entry) {
            if (is_array($entry)) {
                $link = $this->materializeLink($entry, $absoluteFolder, $bySlug, $version);

                if ($link !== null) {
                    $ordered[] = $link;
                }

                continue;
            }

            if (! is_string($entry)) {
                continue;
            }

            if ($entry === '...') {
                unset($explicitLater[$entry]);

                $restKeys = [];

                foreach ($children as $key => $_child) {
                    if (isset($used[$key]) || isset($explicitLater[$key])) {
                        continue;
                    }

                    $restKeys[] = $key;
                }

                sort($restKeys);

                foreach ($restKeys as $key) {
                    $ordered[] = $this->materializeChild($children[$key], $bySlug, $version);
                    $used[$key] = true;
                }

                continue;
            }

            if (preg_match('/^---(.*?)---$/', $entry, $matches) === 1) {
                $ordered[] = [
                    'type' => 'separator',
                    'title' => trim($matches[1]),
                ];

                continue;
            }

            unset($explicitLater[$entry]);

            $key = $entry;

            if (! isset($children[$key])) {
                continue;
            }

            $ordered[] = $this->materializeChild($children[$key], $bySlug, $version);
            $used[$key] = true;
        }

        $remaining = array_keys(array_diff_key($children, $used));
        sort($remaining);

        foreach ($remaining as $key) {
            $ordered[] = $this->materializeChild($children[$key], $bySlug, $version);
        }

        return array_values(array_filter(
            $ordered,
            static fn (?array $node): bool => $node !== null,
        ));
    }

    /**
     * @param  NavChild  $child
     * @param  array<string, Document>  $bySlug
     * @return NavNode|null
     */
    private function materializeChild(array $child, array $bySlug, ?string $version): ?array
    {
        if ($child['isFolder']) {
            $folderPath = $child['folder'] ?? '';
            $folderMeta = $this->readMeta($folderPath);
            $folderSlugPrefix = $child['slug'] ?? $child['key'];

            $title = is_string($folderMeta['title'] ?? null)
                ? $folderMeta['title']
                : Str::titleCase($child['key']);

            $icon = is_string($folderMeta['icon'] ?? null) ? $folderMeta['icon'] : null;
            $defaultOpen = isset($folderMeta['defaultOpen']) && filter_var($folderMeta['defaultOpen'], FILTER_VALIDATE_BOOLEAN);

            return [
                'type' => 'folder',
                'title' => $title,
                'icon' => $icon,
                'defaultOpen' => $defaultOpen,
                'children' => $this->buildFolder($folderPath, $folderSlugPrefix, $bySlug, $version),
            ];
        }

        return $child['node'];
    }

    /**
     * @param  array<string, NavChild>  $children
     * @param  array<string, Document>  $bySlug
     * @return NavTree
     */
    private function defaultOrder(array $children, array $bySlug, ?string $version): array
    {
        uasort($children, function (array $a, array $b): int {
            $orderA = $a['order'];
            $orderB = $b['order'];

            if ($orderA !== null && $orderB !== null && $orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            if ($orderA !== null && $orderB === null) {
                return -1;
            }

            if ($orderA === null && $orderB !== null) {
                return 1;
            }

            return $a['key'] <=> $b['key'];
        });

        $tree = [];

        foreach ($children as $child) {
            $node = $this->materializeChild($child, $bySlug, $version);

            if ($node !== null) {
                $tree[] = $node;
            }
        }

        return $tree;
    }

    /**
     * @param  array<string, Document>  $bySlug
     * @return array<string, NavChild>
     */
    private function discoverChildren(string $absoluteFolder, string $slugPrefix, array $bySlug, ?string $version): array
    {
        $children = [];

        if (! is_dir($absoluteFolder)) {
            return [];
        }

        foreach (scandir($absoluteFolder) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'meta.json' || $entry === '_meta.md') {
                continue;
            }

            $path = $absoluteFolder.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $key = Slug::from($entry);
                $folderSlug = $slugPrefix === '' ? $key : $slugPrefix.'/'.$key;
                $children[$key] = [
                    'key' => $key,
                    'node' => null,
                    'folder' => $path,
                    'isFolder' => true,
                    'slug' => $folderSlug,
                    'order' => null,
                ];

                continue;
            }

            if (! str_ends_with(strtolower($entry), '.md')) {
                continue;
            }

            $basename = substr($entry, 0, -3);

            if ($basename === 'index') {
                $indexSlug = $slugPrefix;
                $document = $bySlug[$indexSlug] ?? null;

                if ($document !== null) {
                    $children['index'] = [
                        'key' => 'index',
                        'node' => $this->pageNode($document, $version),
                        'isFolder' => false,
                        'order' => $this->orderFromMatter($document->frontmatter),
                    ];
                }

                continue;
            }

            $key = Slug::from($basename);
            $slug = $slugPrefix === '' ? $key : $slugPrefix.'/'.$key;
            $document = $bySlug[$slug] ?? null;

            if ($document === null) {
                // Frontmatter slug override: match by scanning documents in this folder prefix
                foreach ($bySlug as $candidate) {
                    if ($this->documentBelongsToKey($candidate, $absoluteFolder, $basename)) {
                        $document = $candidate;
                        $slug = $candidate->slug;
                        break;
                    }
                }
            }

            if ($document === null) {
                continue;
            }

            $children[$key] = [
                'key' => $key,
                'node' => $this->pageNode($document, $version),
                'isFolder' => false,
                'order' => $this->orderFromMatter($document->frontmatter),
            ];
        }

        return $children;
    }

    /**
     * @param  array<string, mixed>  $matter
     */
    private function orderFromMatter(array $matter): ?int
    {
        if (! isset($matter['order'])) {
            return null;
        }

        if (is_int($matter['order'])) {
            return $matter['order'];
        }

        if (is_numeric($matter['order'])) {
            return (int) $matter['order'];
        }

        return null;
    }

    private function documentBelongsToKey(Document $document, string $folder, string $basename): bool
    {
        $expected = $folder.DIRECTORY_SEPARATOR.$basename.'.md';

        return realpath($document->path) === realpath($expected);
    }

    /**
     * @return NavPage
     */
    private function pageNode(Document $document, ?string $version): array
    {
        return [
            'type' => 'page',
            'slug' => $document->slug,
            'title' => $document->title,
            'description' => $document->description,
            'icon' => $document->icon,
            'href' => $this->hrefForSlug($document->slug, $version ?? $document->version),
            'access' => $document->access(),
        ];
    }

    /**
     * A meta.json link takes the access its folder gives, like a page does,
     * unless it sets its own. A link to a gated page also needs the access
     * that page asks for, so nobody is shown a link they cannot follow.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, Document>  $bySlug
     * @return NavPage|null
     */
    private function materializeLink(array $entry, string $absoluteFolder, array $bySlug, ?string $version): ?array
    {
        $title = isset($entry['title']) && is_string($entry['title']) ? $entry['title'] : null;

        if ($title === null || $title === '') {
            return null;
        }

        $slug = isset($entry['slug']) && is_string($entry['slug']) ? $entry['slug'] : '';
        $href = isset($entry['href']) && is_string($entry['href']) && $entry['href'] !== ''
            ? SafeHref::of($entry['href'])
            : ($slug !== '' ? $this->hrefForSlug($slug, null) : null);

        if ($href === null) {
            return null;
        }

        $description = isset($entry['description']) && is_string($entry['description'])
            ? $entry['description']
            : null;
        $icon = isset($entry['icon']) && is_string($entry['icon']) ? $entry['icon'] : null;

        $access = isset($entry['access']) && is_string($entry['access']) && $entry['access'] !== ''
            ? Access::normalize($entry['access'])
            : Access::normalize((new Access)->inherited($absoluteFolder.DIRECTORY_SEPARATOR.'meta.json', $this->contentPath));
        $target = isset($bySlug[$slug]) ? $bySlug[$slug]->access() : 'guest';

        $node = [
            'type' => 'page',
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'href' => $href,
            'access' => $access,
        ];

        if ($target !== 'guest' && $target !== $access) {
            $node['requires'] = [$target];
        }

        return $node;
    }

    private function hrefForSlug(string $slug, ?string $version): string
    {
        return VersionUrl::href($this->routePrefix, $slug, $version, $this->defaultVersion);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMeta(string $folder): ?array
    {
        $path = $folder.DIRECTORY_SEPARATOR.'meta.json';

        if (! is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function rootPath(?string $version): string
    {
        if ($version === null || $version === '') {
            return $this->contentPath;
        }

        return $this->contentPath.DIRECTORY_SEPARATOR.$version;
    }

    private function relativePath(string $root, string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        $absolute = str_replace('\\', '/', $absolute);

        if (str_starts_with($absolute, $root)) {
            return substr($absolute, strlen($root));
        }

        return ltrim($absolute, '/');
    }

    /**
     * @param  NavTree  $tree
     * @return list<NavNode>|null
     */
    private function findPath(array $tree, string $slug): ?array
    {
        foreach ($tree as $node) {
            if ($node['type'] === 'page' && $node['slug'] === $slug) {
                return [$node];
            }

            if ($node['type'] === 'folder') {
                $childPath = $this->findPath($node['children'], $slug);

                if ($childPath !== null) {
                    return [$node, ...$childPath];
                }
            }
        }

        return null;
    }
}
