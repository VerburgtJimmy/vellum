<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Cache\CompiledStore;
use Vellum\Exceptions\DuplicateSlugException;
use Vellum\Exceptions\UnknownDirectiveException;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Markdown\MarkdownRenderer;
use Vellum\Search\SearchVisibility;
use Vellum\Support\Slug;
use Vellum\Support\Str;
use Vellum\Support\VersionUrl;

/**
 * Discovers Markdown documents on disk and resolves them to compiled Documents.
 */
final class ContentRepository
{
    public function __construct(
        private readonly string $contentPath,
        private readonly CompiledStore $store,
        private readonly FrontMatterParser $frontMatterParser = new FrontMatterParser,
        private readonly MarkdownPipeline $pipeline = new MarkdownPipeline,
        private readonly bool $versionsEnabled = false,
        private readonly ?string $latestVersion = null,
        /** @var list<string> */
        private readonly array $versions = [],
        private readonly bool $isLocal = false,
        private readonly string $routePrefix = 'docs',
        private readonly Access $access = new Access,
        private readonly SearchVisibility $visibility = new SearchVisibility,
    ) {}

    /**
     * Pipelines keyed by version folder. Images resolve against the version
     * root, so each version needs its own renderer.
     *
     * @var array<string, MarkdownPipeline>
     */
    private array $versionPipelines = [];

    /**
     * Build a repository from Laravel config values.
     */
    public static function fromConfig(): self
    {
        /** @var array{enabled?: bool, latest?: string, list?: list<string>} $versions */
        $versions = config('vellum.versions', []);

        return new self(
            contentPath: (string) config('vellum.path'),
            store: new CompiledStore((string) config('vellum.cache.path')),
            pipeline: new MarkdownPipeline(new MarkdownRenderer(
                contentPath: (string) config('vellum.path'),
                appUrl: (string) config('app.url', ''),
            )),
            versionsEnabled: (bool) ($versions['enabled'] ?? false),
            latestVersion: $versions['latest'] ?? null,
            versions: $versions['list'] ?? [],
            isLocal: app()->environment('local'),
            routePrefix: (string) config('vellum.route.prefix', 'docs'),
        );
    }

    public function store(): CompiledStore
    {
        return $this->store;
    }

    public function navigationBuilder(): NavigationBuilder
    {
        return new NavigationBuilder(
            $this->contentPath,
            $this->routePrefix,
            $this->urlDefaultVersion(),
        );
    }

    public function searchIndexBuilder(): SearchIndexBuilder
    {
        return new SearchIndexBuilder($this->routePrefix, $this->urlDefaultVersion());
    }

    /**
     * Compile every discovered document and rebuild nav + search index.
     *
     * @return list<Document>
     */
    public function buildAll(?string $version = null): array
    {
        if ($this->versionsEnabled && $version === null) {
            $all = [];

            foreach ($this->versions as $ver) {
                foreach ($this->buildAll($ver) as $document) {
                    $all[] = $document;
                }
            }

            return $all;
        }

        $documents = [];
        $seen = [];

        foreach ($this->discoverSourceFiles($version) as $source) {
            $slug = $source['slug'];

            if (isset($seen[$slug])) {
                throw DuplicateSlugException::forPaths($slug, $seen[$slug], $source['path']);
            }

            $seen[$slug] = $source['path'];
            $documents[] = $this->compileFile($source['path'], $source['slug'], $source['version']);
        }

        $this->rebuildNavAndSearch($documents, $version);

        return $documents;
    }

    /**
     * Load the sidebar tree, rebuilding in local when the directory listing changes.
     *
     * @return list<array<string, mixed>>
     */
    public function navigation(?string $version = null): array
    {
        $version = $this->resolveVersion($version);
        $navBuilder = $this->navigationBuilder();
        $manifest = $this->store->getManifest($version);
        $currentHash = $navBuilder->directoryHash($version);

        if (
            $manifest !== null
            && $manifest['directory_hash'] === $currentHash
            && ($nav = $this->store->getNav($version)) !== null
        ) {
            return $this->visibleNavigation($nav);
        }

        if (! $this->isLocal && ($nav = $this->store->getNav($version)) !== null) {
            return $this->visibleNavigation($nav);
        }

        $documents = $this->documentsForVersion($version);
        $this->rebuildNavAndSearch($documents, $version);

        return $this->visibleNavigation($this->store->getNav($version) ?? []);
    }

    /**
     * @return array{previous: array<string, mixed>|null, next: array<string, mixed>|null}
     */
    public function adjacent(string $slug, ?string $version = null): array
    {
        return $this->navigationBuilder()->adjacent($this->navigation($version), $slug);
    }

    public function allows(Document $document): bool
    {
        return $this->visibility->allowsAccess($document->access());
    }

    /**
     * @return list<array{title: string, href: string|null, slug: string|null}>
     */
    public function breadcrumbs(Document $document): array
    {
        return $this->navigationBuilder()->breadcrumbs(
            $this->navigation($document->version),
            $document->slug,
            $document->title,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function visibleNavigation(array $tree): array
    {
        return $this->visibility->filterNavigation($tree);
    }

    public function searchHash(?string $version = null): ?string
    {
        $version = $this->resolveVersion($version);
        $manifest = $this->store->getManifest($version);

        return $manifest['search_hash'] ?? null;
    }

    /**
     * @param  list<Document>  $documents
     */
    private function rebuildNavAndSearch(array $documents, ?string $version): void
    {
        $navBuilder = $this->navigationBuilder();
        $searchBuilder = $this->searchIndexBuilder();

        $tree = $navBuilder->build($documents, $version);
        $search = $searchBuilder->build($documents, $version);

        $this->store->putNav($tree, $version);
        $this->store->putSearchIndex(['documents' => $search['documents']], $search['hash'], $version);
        $this->store->putManifest([
            'directory_hash' => $navBuilder->directoryHash($version),
            'search_hash' => $search['hash'],
            'version' => $version,
        ], $version);
    }

    /**
     * @return list<Document>
     */
    private function documentsForVersion(?string $version): array
    {
        $documents = [];

        foreach ($this->discoverSourceFiles($version) as $source) {
            $document = $this->find($source['slug'], $source['version']);

            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * Resolve a document by request slug, compiling when required by environment rules.
     */
    public function find(string $slug, ?string $version = null): ?Document
    {
        $slug = trim($slug, '/');

        if (! Slug::isSafe($slug)) {
            return null;
        }

        $version = $this->resolveVersion($version);

        $compiled = $this->store->get($slug, $version);

        if ($compiled !== null && ! $this->shouldRecompile($compiled)) {
            return $compiled;
        }

        $sourcePath = $this->resolveSourcePath($slug, $version);

        if ($sourcePath === null) {
            return $compiled;
        }

        return $this->compileFile($sourcePath, $slug, $version);
    }

    /**
     * Split a request slug into version folder and document slug.
     * The latest version has no URL prefix; other versions keep /{version}/...
     *
     * @return array{version: string|null, slug: string}
     */
    public function parseRequestSlug(string $slug): array
    {
        $slug = trim($slug, '/');

        if (! $this->versionsEnabled) {
            return ['version' => null, 'slug' => $slug];
        }

        $parts = $slug === '' ? [] : explode('/', $slug, 2);
        $first = $parts[0] ?? '';

        if ($first !== '' && in_array($first, $this->versions, true) && ! $this->isDefaultVersion($first)) {
            return [
                'version' => $first,
                'slug' => $parts[1] ?? '',
            ];
        }

        return [
            'version' => $this->latestVersion,
            'slug' => $slug,
        ];
    }

    /**
     * Whether /docs/{latest}/... should 301 to the unprefixed default URL.
     */
    public function shouldRedirectToUnprefixed(string $slug): bool
    {
        if (! $this->versionsEnabled || $this->latestVersion === null) {
            return false;
        }

        $first = explode('/', trim($slug, '/'), 2)[0];

        return $first === $this->latestVersion;
    }

    /**
     * Strip a leading latest-version segment from a request slug.
     */
    public function unprefixedPath(string $slug): string
    {
        $slug = trim($slug, '/');

        if ($this->latestVersion === null) {
            return $slug;
        }

        if ($slug === $this->latestVersion) {
            return '';
        }

        $prefix = $this->latestVersion.'/';

        if (str_starts_with($slug, $prefix)) {
            return substr($slug, strlen($prefix));
        }

        return $slug;
    }

    public function isDefaultVersion(?string $version): bool
    {
        return $this->versionsEnabled
            && $this->latestVersion !== null
            && $version === $this->latestVersion;
    }

    /**
     * Null when versioning is off, even if a 'latest' is still sitting in
     * config. Callers build URLs from this, and a version segment that the
     * router will not match is worse than no segment at all.
     */
    public function latestVersion(): ?string
    {
        return $this->versionsEnabled ? $this->latestVersion : null;
    }

    public function versionsEnabled(): bool
    {
        return $this->versionsEnabled;
    }

    /**
     * @return list<string>
     */
    public function versions(): array
    {
        return $this->versions;
    }

    /**
     * Build a docs URL for a slug (and optional version segment).
     * The latest version omits the version segment.
     */
    public function hrefFor(string $slug, ?string $version = null): string
    {
        return VersionUrl::href($this->routePrefix, $slug, $version, $this->urlDefaultVersion());
    }

    /**
     * Version switcher payload for the docs chrome.
     *
     * @return array{versions: list<string>, currentVersion: string|null, versionHrefs: array<string, string>}
     */
    public function versionSwitcherData(?string $documentSlug, ?string $currentVersion): array
    {
        if (! $this->versionsEnabled) {
            return [
                'versions' => [],
                'currentVersion' => null,
                'versionHrefs' => [],
            ];
        }

        $slug = trim((string) $documentSlug, '/');
        $hrefs = [];

        foreach ($this->versions as $version) {
            $targetSlug = $this->store->exists($slug, $version) || $this->resolveSourcePath($slug, $version) !== null
                ? $slug
                : '';

            $hrefs[$version] = $this->hrefFor($targetSlug, $version);
        }

        return [
            'versions' => $this->versions,
            'currentVersion' => $currentVersion ?? $this->latestVersion,
            'versionHrefs' => $hrefs,
        ];
    }

    /**
     * List every Markdown source under the content root (optionally scoped to a version).
     *
     * @return list<array{path: string, slug: string, version: string|null}>
     */
    public function discoverSourceFiles(?string $version = null): array
    {
        if (! is_dir($this->contentPath)) {
            return [];
        }

        if ($this->versionsEnabled) {
            $versions = $version !== null ? [$version] : $this->versions;
            $files = [];

            foreach ($versions as $ver) {
                $root = $this->contentPath.DIRECTORY_SEPARATOR.$ver;

                if (! is_dir($root)) {
                    continue;
                }

                foreach ($this->scanMarkdownFiles($root) as $absolute) {
                    $relative = $this->relativePath($root, $absolute);
                    $files[] = [
                        'path' => $absolute,
                        'slug' => $this->slugFromRelativePath($relative, $absolute),
                        'version' => $ver,
                    ];
                }
            }

            return $files;
        }

        $files = [];

        foreach ($this->scanMarkdownFiles($this->contentPath) as $absolute) {
            $relative = $this->relativePath($this->contentPath, $absolute);
            $files[] = [
                'path' => $absolute,
                'slug' => $this->slugFromRelativePath($relative, $absolute),
                'version' => null,
            ];
        }

        return $files;
    }

    public function compileFile(string $absolutePath, ?string $slug = null, ?string $version = null): Document
    {
        $parsed = $this->frontMatterParser->parseFile($absolutePath);
        $matter = $parsed['matter'];
        $body = $parsed['body'];

        $relative = $this->versionsEnabled && $version !== null
            ? $this->relativePath($this->contentPath.DIRECTORY_SEPARATOR.$version, $absolutePath)
            : $this->relativePath($this->contentPath, $absolutePath);

        $slug ??= $this->slugFromRelativePath($relative, $absolutePath, $matter);
        $title = $this->resolveTitle($matter, $body, $relative);

        // The title came from the body's own heading and the layout renders an
        // h1 of its own, so drop the heading rather than ship two.
        if ($this->matterTitle($matter) === null && Str::firstHeading($body) !== null) {
            $body = Str::withoutLeadingHeading($body);
        }

        try {
            $rendered = $this->pipelineFor($version)->convert($body);
        } catch (UnknownDirectiveException $exception) {
            throw $exception->withFile($absolutePath);
        }

        $html = $rendered['html'];
        $headings = $rendered['headings'];
        $islands = $rendered['islands'];
        $mtime = (int) filemtime($absolutePath);

        $description = isset($matter['description']) && is_string($matter['description'])
            ? $matter['description']
            : null;

        $icon = isset($matter['icon']) && is_string($matter['icon'])
            ? $matter['icon']
            : null;

        $full = isset($matter['full']) && filter_var($matter['full'], FILTER_VALIDATE_BOOLEAN);
        $matter['access'] = $this->access->forPage($matter, $absolutePath, $this->contentPath);

        $document = new Document(
            slug: $slug,
            title: $title,
            html: $html,
            headings: $headings,
            frontmatter: $matter,
            path: $absolutePath,
            mtime: $mtime,
            description: $description,
            version: $version,
            full: $full,
            icon: $icon,
            islands: $islands,
        );

        $this->store->put($document);

        return $document;
    }

    /**
     * The pipeline a document should render through.
     *
     * Versioned docs live under <root>/<version>, so "assets/x.png" has to
     * resolve there and the emitted URL has to carry the version segment.
     */
    private function pipelineFor(?string $version): MarkdownPipeline
    {
        if (! $this->versionsEnabled || $version === null || $version === '') {
            return $this->pipeline;
        }

        return $this->versionPipelines[$version] ??= new MarkdownPipeline(new MarkdownRenderer(
            contentPath: $this->contentPath.DIRECTORY_SEPARATOR.$version,
            appUrl: (string) config('app.url', ''),
            assetPrefix: $version,
        ));
    }

    private function shouldRecompile(Document $document): bool
    {
        if (! $this->isLocal) {
            return false;
        }

        if (! is_file($document->path)) {
            return true;
        }

        if ((int) filemtime($document->path) > $document->mtime) {
            return true;
        }

        return $this->access->folderMtime($document->path, $this->contentPath) > $document->mtime;
    }

    private function resolveVersion(?string $version): ?string
    {
        if (! $this->versionsEnabled) {
            return null;
        }

        return $version ?? $this->latestVersion;
    }

    private function urlDefaultVersion(): ?string
    {
        return $this->versionsEnabled ? $this->latestVersion : null;
    }

    private function resolveSourcePath(string $slug, ?string $version): ?string
    {
        if (! Slug::isSafe($slug)) {
            return null;
        }

        $roots = [];

        if ($this->versionsEnabled && $version !== null) {
            $roots[] = $this->contentPath.DIRECTORY_SEPARATOR.$version;
        } else {
            $roots[] = $this->contentPath;
        }

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $candidates = $this->candidatePathsForSlug($root, $slug);

            foreach ($candidates as $candidate) {
                $resolved = $this->containedPath($root, $candidate);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        // Frontmatter slug overrides: scan and match
        foreach ($this->discoverSourceFiles($version) as $source) {
            if ($source['slug'] === $slug) {
                return $source['path'];
            }
        }

        return null;
    }

    /**
     * Resolve a candidate to a real file, but only when it stays inside the root.
     * Symlinks that point outside the docs directory are refused here too.
     */
    private function containedPath(string $root, string $candidate): ?string
    {
        if (! is_file($candidate)) {
            return null;
        }

        $real = realpath($candidate);
        $realRoot = realpath($root);

        if ($real === false || $realRoot === false) {
            return null;
        }

        if (! str_starts_with($real, rtrim($realRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return null;
        }

        // The candidate, not the resolved path: a symlinked content root would
        // otherwise stop matching for folder access inheritance.
        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function candidatePathsForSlug(string $root, string $slug): array
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return [
                $root.DIRECTORY_SEPARATOR.'index.md',
            ];
        }

        $normalized = str_replace('/', DIRECTORY_SEPARATOR, $slug);

        return [
            $root.DIRECTORY_SEPARATOR.$normalized.'.md',
            $root.DIRECTORY_SEPARATOR.$normalized.DIRECTORY_SEPARATOR.'index.md',
        ];
    }

    /**
     * @param  array<string, mixed>  $matter
     */
    private function slugFromRelativePath(string $relative, string $absolutePath, array $matter = []): string
    {
        if ($matter === [] && is_file($absolutePath)) {
            $matter = $this->frontMatterParser->parseFile($absolutePath)['matter'];
        }

        if (isset($matter['slug']) && is_string($matter['slug']) && $matter['slug'] !== '') {
            return trim(Slug::from($matter['slug']), '/');
        }

        return Slug::fromRelativePath($relative);
    }

    /**
     * The frontmatter title, if usable. YAML turns an unquoted `title: 2024`
     * into an int, which used to be discarded in favour of the filename.
     *
     * @param  array<string, mixed>  $matter
     */
    private function matterTitle(array $matter): ?string
    {
        $title = $matter['title'] ?? null;

        if (is_int($title) || is_float($title)) {
            $title = (string) $title;
        }

        if (! is_string($title)) {
            return null;
        }

        $title = trim($title);

        return $title === '' ? null : $title;
    }

    /**
     * @param  array<string, mixed>  $matter
     */
    private function resolveTitle(array $matter, string $body, string $relative): string
    {
        $title = $this->matterTitle($matter);

        if ($title !== null) {
            return $title;
        }

        $heading = Str::firstHeading($body);

        if ($heading !== null) {
            return $heading;
        }

        $basename = pathinfo($relative, PATHINFO_FILENAME);

        if ($basename === 'index') {
            $basename = basename(dirname($relative));

            if ($basename === '.' || $basename === DIRECTORY_SEPARATOR || $basename === '') {
                $basename = 'Index';
            }
        }

        return Str::titleCase($basename);
    }

    /**
     * @return list<string>
     */
    private function scanMarkdownFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            if (strtolower($file->getFilename()) === '_meta.md') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
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
}
