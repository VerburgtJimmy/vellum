<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Cache\CompiledStore;
use Vellum\Cache\FragmentCache;
use Vellum\CoreServiceProvider;
use Vellum\Exceptions\DuplicateSlugException;
use Vellum\Exceptions\UnknownDirectiveException;
use Vellum\Markdown\Islands\IslandRenderer;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Markdown\MarkdownRenderer;
use Vellum\Search\SearchVisibility;
use Vellum\Site;
use Vellum\Support\Slug;
use Vellum\Support\Str;
use Vellum\Support\VersionUrl;

/**
 * Discovers Markdown documents on disk and resolves them to compiled Documents.
 */
final class ContentRepository
{
    private readonly string $contentPath;

    private readonly CompiledStore $store;

    private readonly MarkdownPipeline $pipeline;

    private readonly bool $versionsEnabled;

    private readonly ?string $latestVersion;

    /** @var list<string> */
    private readonly array $versions;

    private readonly bool $isLocal;

    private readonly string $routePrefix;

    /**
     * Everything the repository needs to know comes from the site it is
     * given. The store and the pipeline can be handed in, for a test that
     * wants to look inside them; otherwise they are made from the site.
     */
    public function __construct(
        private readonly Site $site,
        ?CompiledStore $store = null,
        private readonly FrontMatterParser $frontMatterParser = new FrontMatterParser,
        ?MarkdownPipeline $pipeline = null,
        private readonly Access $access = new Access,
        private readonly SearchVisibility $visibility = new SearchVisibility,
        private readonly ImageReport $imageReport = new ImageReport,
        /** @var list<PageSource> */
        private readonly array $sources = [],
    ) {
        $this->contentPath = $site->contentPath;
        $this->store = $store ?? new CompiledStore($site->cachePath);
        $this->versionsEnabled = $site->versions->enabled;
        $this->latestVersion = $site->versions->latest;
        $this->versions = $site->versions->list;
        $this->isLocal = $site->isLocal;
        $this->routePrefix = $site->routePrefix;
        $this->pipeline = $pipeline ?? $this->newPipeline($this->contentPath, null);
    }

    /**
     * Pipelines keyed by version folder. Images resolve against the version
     * root, so each version needs its own renderer.
     *
     * @var array<string, MarkdownPipeline>
     */
    private array $versionPipelines = [];

    private ?LastUpdated $lastUpdated = null;

    /**
     * Sidebar trees already loaded by this repository, by version. A page
     * asks for the tree three times: sidebar, breadcrumbs and prev/next.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $navigationTrees = [];

    /**
     * A repository built from the app's config as it is now. Inside a request,
     * prefer resolving ContentRepository from the container, which builds one
     * per request and shares it.
     */
    public static function fromConfig(): self
    {
        return CoreServiceProvider::repository(app(), CoreServiceProvider::site(app()));
    }

    public function site(): Site
    {
        return $this->site;
    }

    /**
     * The names of the sources of generated pages this repository has.
     *
     * @return list<string>
     */
    public function sourceNames(): array
    {
        return array_map(static fn (PageSource $source): string => $source->name(), $this->sources);
    }

    public function store(): CompiledStore
    {
        return $this->store;
    }

    /**
     * Where the Markdown lives. A version's files sit in a folder under it.
     */
    public function contentPath(?string $version = null): string
    {
        return $version === null || $version === ''
            ? $this->contentPath
            : $this->contentPath.DIRECTORY_SEPARATOR.$version;
    }

    /**
     * What the image renderer could not resolve during this process.
     */
    public function imageReport(): ImageReport
    {
        return $this->imageReport;
    }

    public function navigationBuilder(): NavigationBuilder
    {
        return new NavigationBuilder(
            $this->contentPath,
            $this->routePrefix,
            $this->urlDefaultVersion(),
        );
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

        // One build, one report: a rebuild must not inherit the last one.
        $this->imageReport->reset();

        // A build compiles every page, so ask git for every date at once.
        $this->lastUpdated()->prime();

        foreach ($this->discoverSourceFiles($version) as $source) {
            $slug = $source['slug'];

            if (isset($seen[$slug])) {
                throw DuplicateSlugException::forPaths($slug, $seen[$slug], $source['path']);
            }

            $seen[$slug] = $source['path'];
            $documents[] = $this->compileFile($source['path'], $source['slug'], $source['version']);
        }

        foreach ($this->generated($version) as $document) {
            if (isset($seen[$document->slug])) {
                throw DuplicateSlugException::forPaths($document->slug, $seen[$document->slug], $document->path);
            }

            $seen[$document->slug] = $document->path;
            $documents[] = $document;
        }

        $this->store->prunePages(array_map('strval', array_keys($seen)), $version);
        $this->rebuildNavigation($documents, $version);

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
        $key = $version ?? '';

        return $this->visibleNavigation($this->navigationTrees[$key] ??= $this->loadNavigation($version));
    }

    /**
     * The tree for the sidebar of one page. That is navigation(), unless a
     * folder has a sidebar to itself: a top-level folder whose meta.json says
     * `root`, or the folder of a source of generated pages that asks for one,
     * as the API reference can. Then a page in that folder gets the folder
     * alone, and every other page gets the docs without any of them.
     *
     * @return list<array<string, mixed>>
     */
    public function sidebar(?string $slug, ?string $version = null): array
    {
        $tree = $this->navigation($version);
        $separate = $this->separateFolders($tree);

        foreach ($separate as $index) {
            if ($this->holdsPage($tree[$index], $slug)) {
                /** @var list<array<string, mixed>> $children */
                $children = $tree[$index]['children'];

                return $children;
            }
        }

        foreach ($separate as $index) {
            unset($tree[$index]);
        }

        return array_values($tree);
    }

    /**
     * The sidebars a reader can switch between, with the one this page is in
     * marked: the docs' own first, then each folder with a sidebar of its own
     * in the order the sidebar lists them. A folder this reader may open no
     * page of is left out. Empty when there is only one.
     *
     * @return list<array{key: string, title: string, description: string|null, href: string, current: bool}>
     */
    public function sections(?string $slug, ?string $version = null): array
    {
        $tree = $this->navigation($version);
        $others = [];

        foreach ($this->separateFolders($tree) as $key => $index) {
            $folder = $tree[$index];
            $first = $this->navigationBuilder()->flattenPages([$folder])[0] ?? null;

            if ($first === null) {
                continue;
            }

            $title = $folder['title'] ?? null;
            $description = $folder['description'] ?? null;
            $others[] = [
                'key' => $key,
                'title' => is_string($title) && $title !== '' ? $title : $key,
                'description' => is_string($description) ? $description : null,
                'href' => $first['href'],
                'current' => $this->holdsPage($folder, $slug),
            ];
        }

        if ($others === []) {
            return [];
        }

        $inOther = in_array(true, array_column($others, 'current'), true);

        return [
            [
                'key' => '',
                'title' => $this->navigationBuilder()->title($this->resolveVersion($version)),
                'description' => null,
                'href' => $this->hrefFor('', $version),
                'current' => ! $inOther,
            ],
            ...$others,
        ];
    }

    /**
     * Where the folders with a sidebar of their own sit in a tree, by their
     * key: the folder name for a root, the source's name for generated pages.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return array<string, int>
     */
    private function separateFolders(array $tree): array
    {
        $separate = [];
        $sources = [];

        foreach ($this->sources as $source) {
            $sources[$source->name()] = $source->separate();
        }

        foreach ($tree as $index => $node) {
            $key = $node['section'] ?? null;

            if (! is_string($key) || ! is_array($node['children'] ?? null)) {
                continue;
            }

            if (($node['root'] ?? false) === true || ($sources[$key] ?? false)) {
                $separate[$key] = $index;
            }
        }

        return $separate;
    }

    /**
     * Folders below the top level that ask to be a root, by version, for the
     * build to report.
     *
     * @return list<string>
     */
    public function misplacedRoots(): array
    {
        $found = [];

        foreach ($this->versionsEnabled ? $this->versions : [null] as $version) {
            foreach ($this->navigationBuilder()->misplacedRoots($version) as $path) {
                $found[] = $version === null ? $path : $version.'/'.$path;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $folder
     */
    private function holdsPage(array $folder, ?string $slug): bool
    {
        if ($slug === null) {
            return false;
        }

        foreach ($this->navigationBuilder()->flattenPages([$folder]) as $page) {
            if ($page['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * The whole tree, before access is applied. Production trusts the one the
     * build wrote; only local walks the directory to notice a changed file.
     *
     * @return list<array<string, mixed>>
     */
    private function loadNavigation(?string $version): array
    {
        $stored = $this->store->getNav($version);

        if (! $this->isLocal && $stored !== null) {
            return $stored;
        }

        $manifest = $this->store->getManifest($version);

        if (
            $stored !== null
            && $manifest !== null
            && $manifest['directory_hash'] === $this->navigationBuilder()->directoryHash($version)
            && ($manifest['sources'] ?? []) === $this->signatures($version)
        ) {
            return $stored;
        }

        $failed = false;
        $documents = $this->documentsForVersion($version, $failed);
        $this->rebuildNavigation($documents, $version, complete: ! $failed);

        return $this->store->getNav($version) ?? [];
    }

    /**
     * @return array{previous: array<string, mixed>|null, next: array<string, mixed>|null}
     */
    public function adjacent(string $slug, ?string $version = null): array
    {
        // Within the sidebar the page is in, so the last page of the docs
        // does not lead into the reference when the two are kept apart.
        return $this->navigationBuilder()->adjacent($this->sidebar($slug, $version), $slug);
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
        $tree = $this->navigation($document->version);

        // A page in a folder with a sidebar of its own starts at that folder.
        foreach ($this->separateFolders($tree) as $index) {
            $folder = $tree[$index];

            if (! $this->holdsPage($folder, $document->slug)) {
                continue;
            }

            /** @var list<array<string, mixed>> $children */
            $children = $folder['children'];
            $first = $this->navigationBuilder()->flattenPages($children)[0] ?? null;
            $title = $folder['title'] ?? null;

            return $this->navigationBuilder()->breadcrumbs($children, $document->slug, $document->title, [
                'title' => is_string($title) ? $title : '',
                'href' => $first['href'] ?? null,
                'slug' => $first['slug'] ?? null,
            ]);
        }

        return $this->navigationBuilder()->breadcrumbs($tree, $document->slug, $document->title);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function visibleNavigation(array $tree): array
    {
        return $this->visibility->filterNavigation($tree);
    }

    /**
     * @param  list<Document>  $documents
     */
    private function rebuildNavigation(array $documents, ?string $version, bool $complete = true): void
    {
        $navBuilder = $this->navigationBuilder();

        // Written pages make the tree. Generated ones have no folders or
        // meta.json to read an order from, so they bring a group of their own.
        $tree = $navBuilder->build(
            array_values(array_filter($documents, static fn (Document $document): bool => ! $document->generated())),
            $version,
        );

        // A generated page has no source file that find() could compile it
        // from later, so it is written here, wherever the tree is rebuilt.
        foreach ($this->generated($version) as $document) {
            $this->store->put($document);
        }

        foreach ($this->carriedSources($version) as $source) {
            $href = fn (string $slug): string => $this->hrefFor($slug, $version);
            $folder = $source->documents($version, $href) === [] ? null : $source->navigation($version, $href);

            if ($folder !== null) {
                // Marked with the source, so it can be given a sidebar of its own.
                $tree[] = ['type' => $folder['type'] ?? 'folder', 'section' => $source->name()] + $folder;
            }
        }

        $this->store->putNav($tree, $version);
        unset($this->navigationTrees[$version ?? '']);
        $this->store->putManifest([
            'directory_hash' => $navBuilder->directoryHash($version),
            'version' => $version,
            // Every page compiled, so production can serve only these.
            'complete' => $complete,
            'sources' => $this->signatures($version),
        ], $version);
    }

    /**
     * Pages from the sources of generated pages. What they are built from is
     * not versioned, so they belong to the latest version only and are not
     * copied into the others.
     *
     * @return list<Document>
     */
    private function generated(?string $version): array
    {
        $documents = [];

        foreach ($this->carriedSources($version) as $source) {
            array_push($documents, ...$source->documents($version, fn (string $slug): string => $this->hrefFor($slug, $version)));
        }

        return $documents;
    }

    /**
     * What the sources had to work around: a reference it could not follow,
     * a spec it could not find. For the build to print.
     *
     * @return list<string>
     */
    public function generatedWarnings(): array
    {
        $warnings = [];

        foreach ($this->sources as $source) {
            array_push($warnings, ...$source->warnings());
        }

        return $warnings;
    }

    /**
     * Each source's signature, by name, as a build records it in the
     * manifest. A version that carries no generated pages records null.
     *
     * @return array<string, string|null>
     */
    private function signatures(?string $version): array
    {
        $signatures = [];

        foreach ($this->sources as $source) {
            $signatures[$source->name()] = $this->carriesGenerated($version) ? $source->signature() : null;
        }

        return $signatures;
    }

    /**
     * @return list<PageSource>
     */
    private function carriedSources(?string $version): array
    {
        return $this->carriesGenerated($version) ? $this->sources : [];
    }

    private function carriesGenerated(?string $version): bool
    {
        return ! $this->versionsEnabled || $version === null || $version === $this->latestVersion;
    }

    private function sourceFor(string $slug): ?PageSource
    {
        foreach ($this->sources as $source) {
            if ($source->owns($slug)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Whether a generated page in the store is still one its source would
     * build. Production serves what the build wrote. Local asks the source,
     * so an endpoint that was removed from a spec stops answering.
     */
    private function generatedIsCurrent(Document $document, ?string $version): bool
    {
        if (! $this->isLocal) {
            return true;
        }

        $source = $this->sourceFor($document->slug);

        return $source !== null && $this->carriesGenerated($version) && $source->isCurrent($document);
    }

    /**
     * @return list<Document>
     */
    private function documentsForVersion(?string $version, bool &$failed = false): array
    {
        $documents = [];
        $failed = false;

        foreach ($this->discoverSourceFiles($version) as $source) {
            // Building the sidebar on a request compiles every page. One with
            // a typo in it must fail on its own URL, not on all of them, so
            // it is logged and left out here.
            try {
                $document = $this->find($source['slug'], $source['version']);
            } catch (\Throwable $exception) {
                report($exception);
                $failed = true;

                continue;
            }

            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * The page's finished HTML, with the Blade components in it rendered.
     * Unless $cache is off, a page that uses only Vellum's own components
     * comes from the fragment cache; one that uses the app's is rendered
     * every time, so the component can depend on the request.
     */
    public function render(Document $document, bool $cache = true): string
    {
        $components = $this->site->components;
        $render = static fn (): string => (new IslandRenderer($components))->render($document->html, $document->islands);
        $html = $cache ? (new FragmentCache($components))->remember($document, $render) : $render();

        return $this->describeCards($html, $document->version);
    }

    /**
     * Fills in each card that asked for the linked page's own description,
     * from the pages this reader may see, so a gated page's description is
     * never shown to someone who cannot open it. A link outside the docs is
     * described by its domain. This runs after the fragment cache and reads
     * the navigation, so a card follows its page without its own page being
     * compiled again.
     */
    private function describeCards(string $html, ?string $version): string
    {
        if (! str_contains($html, 'data-vellum-card-describe')) {
            return $html;
        }

        $descriptions = [];

        foreach ($this->navigationBuilder()->flattenPages($this->navigation($version)) as $page) {
            $description = $page['description'] ?? null;

            if (is_string($description) && $description !== '') {
                $descriptions[rtrim($page['href'], '/')] = $description;
            }
        }

        return (string) preg_replace_callback(
            '/<span class="vellum-card-description" data-vellum-card-describe="([^"]*)"><\/span>/',
            static function (array $match) use ($descriptions): string {
                $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
                $path = rtrim(preg_split('/[?#]/', $href)[0] ?? '', '/');
                $host = preg_match('#^https?://#i', $href) === 1 ? parse_url($href, PHP_URL_HOST) : null;
                $text = $descriptions[$path] ?? (is_string($host) ? $host : null);

                return $text === null ? '' : '<span class="vellum-card-description">'.e($text).'</span>';
            },
            $html,
        );
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

        // Nothing compiles a generated page on its own: the pages of a source
        // are built together, where the sidebar is. So bring that up to date
        // and look again.
        if (
            $this->sourceFor($slug) !== null
            && ($this->isLocal || ($compiled === null && ! $this->servesBuildOnly($version)))
        ) {
            $this->navigation($version);
            $compiled = $this->store->get($slug, $version);
        }

        if ($compiled !== null && $compiled->generated()) {
            return $this->generatedIsCurrent($compiled, $version) ? $compiled : null;
        }

        if ($compiled !== null && ! $this->shouldRecompile($compiled)) {
            return $compiled;
        }

        if ($compiled === null && $this->servesBuildOnly($version)) {
            return null;
        }

        $sourcePath = $this->resolveSourcePath($slug, $version);

        // The source is gone, so the compiled copy is of a page that no longer
        // exists.
        if ($sourcePath === null) {
            return null;
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
     * @return array{versions: list<string>, currentVersion: string|null, versionHrefs: array<string, string>, versionPages: list<string>}
     */
    public function versionSwitcherData(?string $documentSlug, ?string $currentVersion): array
    {
        if (! $this->versionsEnabled) {
            return [
                'versions' => [],
                'currentVersion' => null,
                'versionHrefs' => [],
                'versionPages' => [],
            ];
        }

        $slug = trim((string) $documentSlug, '/');
        $hrefs = [];
        $pages = [];

        foreach ($this->versions as $version) {
            $exists = $this->store->exists($slug, $version)
                || (! $this->servesBuildOnly($version) && $this->resolveSourcePath($slug, $version) !== null);

            // A version without this page links to its start page instead.
            $hrefs[$version] = $this->hrefFor($exists ? $slug : '', $version);

            if ($exists || $slug === '') {
                $pages[] = $version;
            }
        }

        return [
            'versions' => $this->versions,
            'currentVersion' => $currentVersion ?? $this->latestVersion,
            'versionHrefs' => $hrefs,
            'versionPages' => $pages,
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

        // A renderer has no idea which document it is inside, so tell it.
        $this->imageReport->forPage($slug, $version);

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
            updated: $this->lastUpdated()->resolve($absolutePath, $matter),
        );

        $this->store->put($document);

        return $document;
    }

    /**
     * One resolver per repository, so git is looked for once per build.
     */
    private function lastUpdated(): LastUpdated
    {
        return $this->lastUpdated ??= new LastUpdated($this->contentPath);
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

        return $this->versionPipelines[$version] ??= $this->newPipeline($this->contentPath.DIRECTORY_SEPARATOR.$version, $version);
    }

    private function newPipeline(string $contentPath, ?string $assetPrefix): MarkdownPipeline
    {
        return new MarkdownPipeline(new MarkdownRenderer(
            contentPath: $contentPath,
            appUrl: $this->site->appUrl,
            assetPrefix: $assetPrefix,
            imageReport: $this->imageReport,
            routePrefix: $this->routePrefix,
        ), $this->site->components);
    }

    /**
     * Production serves what the build compiled. Once a build has run, a slug
     * it did not compile is not a page, and finding that out from the sources
     * would read every file's frontmatter on every 404.
     */
    private function servesBuildOnly(?string $version): bool
    {
        return ! $this->isLocal && ($this->store->getManifest($version)['complete'] ?? false);
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
