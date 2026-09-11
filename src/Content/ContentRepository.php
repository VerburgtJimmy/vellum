<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Cache\CompiledStore;
use Vellum\Markdown\MarkdownRenderer;
use Vellum\Support\Slug;
use Vellum\Support\Str;

/**
 * Discovers Markdown documents on disk and resolves them to compiled Documents.
 */
final class ContentRepository
{
    public function __construct(
        private readonly string $contentPath,
        private readonly CompiledStore $store,
        private readonly FrontMatterParser $frontMatterParser = new FrontMatterParser,
        private readonly MarkdownRenderer $markdownRenderer = new MarkdownRenderer,
        private readonly bool $versionsEnabled = false,
        private readonly ?string $latestVersion = null,
        /** @var list<string> */
        private readonly array $versions = [],
        private readonly bool $isLocal = false,
        private readonly string $routePrefix = 'docs',
    ) {}

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
            markdownRenderer: new MarkdownRenderer(
                contentPath: (string) config('vellum.path'),
                appUrl: (string) config('app.url', ''),
            ),
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
        return new NavigationBuilder($this->contentPath, $this->routePrefix);
    }

    public function searchIndexBuilder(): SearchIndexBuilder
    {
        return new SearchIndexBuilder($this->routePrefix);
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

        foreach ($this->discoverSourceFiles($version) as $source) {
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
            return $nav;
        }

        if (! $this->isLocal && ($nav = $this->store->getNav($version)) !== null) {
            return $nav;
        }

        $documents = $this->documentsForVersion($version);
        $this->rebuildNavAndSearch($documents, $version);

        return $this->store->getNav($version) ?? [];
    }

    /**
     * @return array{previous: array<string, mixed>|null, next: array<string, mixed>|null}
     */
    public function adjacent(string $slug, ?string $version = null): array
    {
        return $this->navigationBuilder()->adjacent($this->navigation($version), $slug);
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
     * Whether an unversioned slug should redirect to the latest version.
     */
    public function shouldRedirectToLatest(string $slug): bool
    {
        if (! $this->versionsEnabled || $this->latestVersion === null) {
            return false;
        }

        $first = explode('/', trim($slug, '/'), 2)[0];

        return $first !== '' && ! in_array($first, $this->versions, true);
    }

    public function latestVersion(): ?string
    {
        return $this->latestVersion;
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
        $rendered = $this->markdownRenderer->convert($body);
        $html = $rendered['html'];
        $headings = $rendered['headings'];
        $title = $this->resolveTitle($matter, $body, $relative);
        $mtime = (int) filemtime($absolutePath);

        $description = isset($matter['description']) && is_string($matter['description'])
            ? $matter['description']
            : null;

        $icon = isset($matter['icon']) && is_string($matter['icon'])
            ? $matter['icon']
            : null;

        $full = isset($matter['full']) && filter_var($matter['full'], FILTER_VALIDATE_BOOLEAN);

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
        );

        $this->store->put($document);

        return $document;
    }

    private function shouldRecompile(Document $document): bool
    {
        if (! $this->isLocal) {
            return false;
        }

        if (! is_file($document->path)) {
            return true;
        }

        return (int) filemtime($document->path) > $document->mtime;
    }

    private function resolveVersion(?string $version): ?string
    {
        if (! $this->versionsEnabled) {
            return null;
        }

        return $version ?? $this->latestVersion;
    }

    private function resolveSourcePath(string $slug, ?string $version): ?string
    {
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
                if (is_file($candidate)) {
                    return $candidate;
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
     * @param  array<string, mixed>  $matter
     */
    private function resolveTitle(array $matter, string $body, string $relative): string
    {
        if (isset($matter['title']) && is_string($matter['title']) && $matter['title'] !== '') {
            return $matter['title'];
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
