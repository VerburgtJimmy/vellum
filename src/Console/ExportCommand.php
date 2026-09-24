<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\File;
use Vellum\Answers\AnswerIndex;
use Vellum\Changelog\Changelog;
use Vellum\Changelog\ChangelogFeed;
use Vellum\Content\ContentFiles;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\HeadingExtractor;
use Vellum\Http\DocsView;
use Vellum\Semantic\SemanticSet;
use Vellum\Support\LlmsTxt;
use Vellum\Support\Sitemap;

/**
 * Renders the docs site to a static HTML folder for any static host.
 */
final class ExportCommand extends Command
{
    protected $signature = 'vellum:export {--out= : Output directory (defaults to config vellum.export.out)}';

    protected $description = 'Export documentation to a static HTML folder';

    /**
     * The list of files an export wrote, kept at the export root so the next
     * export can remove the ones it no longer writes.
     */
    private const MANIFEST = '.vellum-export.json';

    /**
     * Every file this run wrote, by absolute path.
     *
     * @var array<string, true>
     */
    private array $written = [];

    public function __construct(
        private readonly ViewFactory $view,
        private readonly HeadingExtractor $headingExtractor = new HeadingExtractor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $started = microtime(true);
        $this->written = [];
        $repository = ContentRepository::fromConfig();
        $documents = $repository->buildAll();

        $outOption = $this->option('out');
        $out = is_string($outOption) && $outOption !== ''
            ? $outOption
            : (string) config('vellum.export.out', public_path('docs-static'));

        $baseUrl = (string) config('vellum.export.base_url', '/');
        $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');
        $packageRoot = dirname(__DIR__, 2);

        if (! is_dir($out) && ! mkdir($out, 0755, true) && ! is_dir($out)) {
            $this->error('Unable to create export directory: '.$out);

            return self::FAILURE;
        }

        $prefixRoot = $out.DIRECTORY_SEPARATOR.$prefix;

        if (! is_dir($prefixRoot) && ! mkdir($prefixRoot, 0755, true) && ! is_dir($prefixRoot)) {
            $this->error('Unable to create prefix directory: '.$prefixRoot);

            return self::FAILURE;
        }

        $pages = 0;

        foreach ($documents as $document) {
            $access = $document->access();

            if (! $repository->allows($document)) {
                $label = $document->version !== null && $document->version !== ''
                    ? $document->version.'/'.($document->slug === '' ? 'index' : $document->slug)
                    : ($document->slug === '' ? 'index' : $document->slug);
                $this->warn("Dropped gated page: {$label} (access: {$access})");

                continue;
            }

            $html = $this->renderDocument($repository, $document);
            $path = $this->documentOutputPath($prefixRoot, $document, $repository);
            $html = $this->rewriteHtml($html, $out, $path, $prefix, $baseUrl);

            $this->writeFile($path, $html);
            $this->writeRawMarkdown($prefixRoot, $document);
            $pages++;
        }

        if ($repository->versionsEnabled()) {
            $latest = $repository->latestVersion();

            if ($latest !== null) {
                foreach ($documents as $document) {
                    if ($document->version !== $latest || ! $repository->allows($document)) {
                        continue;
                    }

                    $path = $this->prefixedOutputPath($prefixRoot, $document);
                    $targetHref = $this->hrefBetween($path, $this->documentOutputPath($prefixRoot, $document, $repository), $out);
                    $canonical = DocsView::canonical($repository->hrefFor($document->slug, $document->version), staticExport: true);
                    $this->writeFile($path, $this->redirectHtml($targetHref, $canonical));
                    $pages++;
                }
            }
        }

        $pages += $this->exportChangelog($repository, $prefixRoot, $out, $prefix, $baseUrl);
        $this->export404($repository, $out, $prefix, $baseUrl);

        $this->writeSitemap($repository, $out);
        $this->writeLlmsTxt($repository, $out);
        $this->copyDist($packageRoot, $out);
        $this->copyContentFiles((string) config('vellum.path'), $prefixRoot);
        $this->writeAnswers($repository, $documents, $prefixRoot);
        $this->removeStaleFiles($out);

        $elapsed = round((microtime(true) - $started) * 1000);

        $this->info(sprintf(
            'Exported %d page%s (%d document%s) to %s in %d ms',
            $pages,
            $pages === 1 ? '' : 's',
            count($documents),
            count($documents) === 1 ? '' : 's',
            $out,
            $elapsed,
        ));

        return self::SUCCESS;
    }

    private function renderDocument(ContentRepository $repository, Document $document): string
    {
        return $this->view->file(
            dirname(__DIR__, 2).'/resources/views/pages/doc.blade.php',
            DocsView::document($repository, $document, $this->headingExtractor, cacheFragment: false, staticExport: true),
        )->render();
    }

    private function exportChangelog(
        ContentRepository $repository,
        string $prefixRoot,
        string $out,
        string $prefix,
        string $baseUrl,
    ): int {
        $changelog = Changelog::load();

        if ($changelog === null) {
            return 0;
        }

        $path = $prefixRoot.DIRECTORY_SEPARATOR.'changelog'.DIRECTORY_SEPARATOR.'index.html';
        $html = $this->view->file(
            dirname(__DIR__, 2).'/resources/views/pages/changelog.blade.php',
            DocsView::changelog($repository, $changelog, staticExport: true),
        )->render();
        $html = $this->rewriteHtml($html, $out, $path, $prefix, $baseUrl);
        $this->writeFile($path, $html);
        $this->writeFile(
            $prefixRoot.DIRECTORY_SEPARATOR.'changelog.atom',
            (new ChangelogFeed)->render($changelog),
        );
        $this->writeFile(
            $prefixRoot.DIRECTORY_SEPARATOR.'_vellum'.DIRECTORY_SEPARATOR.'raw'.DIRECTORY_SEPARATOR.'changelog.md',
            $changelog->rawMarkdown(),
        );

        return 1;
    }

    /**
     * A 404 at the export root, which is where static hosts look for one.
     * Without it they fall back to their own page and the reader leaves the site.
     */
    private function export404(ContentRepository $repository, string $out, string $prefix, string $baseUrl): void
    {
        $version = $repository->latestVersion();
        $switcher = $repository->versionSwitcherData('', $version);

        $html = $this->view->file(
            dirname(__DIR__, 2).'/resources/views/pages/404.blade.php',
            [
                'name' => config('vellum.name'),
                'description' => 'Page not found',
                'pageTitle' => DocsView::pageTitle('Page not found'),
                'noindex' => true,
                'navigation' => $repository->navigation($version),
                'versions' => $switcher['versions'],
                'currentVersion' => $switcher['currentVersion'],
                'versionHrefs' => $switcher['versionHrefs'],
                'searchPlacement' => config('vellum.layout.search', 'sidebar') === 'header' ? 'header' : 'sidebar',
                'staticExport' => true,
            ],
        )->render();

        $path = $out.DIRECTORY_SEPARATOR.'404.html';

        $this->writeFile($path, $this->rewriteHtml($html, $out, $path, $prefix, $baseUrl, rootRelative: true));
    }

    private function writeRawMarkdown(string $prefixRoot, Document $document): void
    {
        $slug = DocsView::rawSlug($document);
        $target = $prefixRoot.DIRECTORY_SEPARATOR.'_vellum'.DIRECTORY_SEPARATOR.'raw'
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $slug).'.md';

        $this->writeFile($target, DocsView::source($document));
    }

    private function documentOutputPath(string $prefixRoot, Document $document, ContentRepository $repository): string
    {
        $segments = [];

        if (is_string($document->version) && $document->version !== '' && ! $repository->isDefaultVersion($document->version)) {
            $segments[] = $document->version;
        }

        $slug = trim($document->slug, '/');

        if ($slug !== '') {
            $segments[] = str_replace('/', DIRECTORY_SEPARATOR, $slug);
        }

        $directory = $segments === []
            ? $prefixRoot
            : $prefixRoot.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);

        return $directory.DIRECTORY_SEPARATOR.'index.html';
    }

    private function prefixedOutputPath(string $prefixRoot, Document $document): string
    {
        $segments = [];

        if (is_string($document->version) && $document->version !== '') {
            $segments[] = $document->version;
        }

        $slug = trim($document->slug, '/');

        if ($slug !== '') {
            $segments[] = str_replace('/', DIRECTORY_SEPARATOR, $slug);
        }

        $directory = $segments === []
            ? $prefixRoot
            : $prefixRoot.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);

        return $directory.DIRECTORY_SEPARATOR.'index.html';
    }

    /**
     * A stub sending the version-prefixed URL of a latest page to its
     * unprefixed one. The canonical names the page as the sitemap does, and
     * is left out when there is no origin to make it absolute with.
     */
    private function redirectHtml(string $targetHref, ?string $canonical): string
    {
        $escaped = htmlspecialchars($targetHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $canonicalLink = $canonical === null
            ? ''
            : "\n    <link rel=\"canonical\" href=\"".htmlspecialchars($canonical, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={$escaped}">{$canonicalLink}
    <title>Redirecting</title>
</head>
<body>
    <p><a href="{$escaped}">Continue to the latest version</a></p>
</body>
</html>
HTML;
    }

    private function copyDist(string $packageRoot, string $out): void
    {
        $source = $packageRoot.'/resources/dist';
        $target = $out.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'vellum';

        if (! is_dir($source)) {
            $this->warn('Package dist missing: '.$source);

            return;
        }

        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        foreach (File::files($source) as $file) {
            copy($file->getPathname(), $target.DIRECTORY_SEPARATOR.$file->getFilename());
            $this->written[$target.DIRECTORY_SEPARATOR.$file->getFilename()] = true;
        }
    }

    private function copyContentFiles(string $contentPath, string $prefixRoot): void
    {
        if (! is_dir($contentPath)) {
            return;
        }

        $filesRoot = $prefixRoot.DIRECTORY_SEPARATOR.'_vellum'.DIRECTORY_SEPARATOR.'files';
        $contentPath = rtrim(str_replace('\\', '/', $contentPath), '/');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contentPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $absolute = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($absolute, strlen($contentPath)), '/');

            // The same rule as the asset route: never a source, a dotfile or
            // anything under a dot-directory such as .vellum.
            if (! ContentFiles::isPublic($relative)) {
                continue;
            }

            $destination = $filesRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $directory = dirname($destination);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            copy($file->getPathname(), $destination);
            $this->written[$destination] = true;
        }
    }

    /**
     * The answer index and the vectors search reads in the browser. A static
     * host has no session, so these are what a guest may see, the same rule
     * the exported pages follow.
     *
     * @param  list<Document>  $documents
     */
    private function writeAnswers(ContentRepository $repository, array $documents, string $prefixRoot): void
    {
        if (! (bool) config('vellum.answers.enabled', true)) {
            return;
        }

        $version = $repository->versionsEnabled() ? $repository->latestVersion() : null;
        $directory = $repository->store()->versionPath($version);

        // The built index carries the questions a model wrote; without one,
        // export what these documents say on their own.
        $index = AnswerIndex::load($directory.'/answers') ?? AnswerIndex::build(
            array_values(array_filter($documents, static fn (Document $document): bool => $document->version === $version)),
            $repository,
        );

        $guest = $index->forAccess(['guest']);
        $this->writeFile($prefixRoot.DIRECTORY_SEPARATOR.'_vellum'.DIRECTORY_SEPARATOR.AnswerIndex::FILE, (string) json_encode(
            [
                'sections' => $guest->sections,
                'synonyms' => $guest->synonymGroups(),
                'threshold' => (float) config('vellum.answers.card_threshold', 0.75),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        $set = (bool) config('vellum.answers.semantic', true) ? SemanticSet::load($directory.'/semantic') : null;

        if ($set !== null) {
            $this->writeFile($prefixRoot.DIRECTORY_SEPARATOR.'_vellum'.DIRECTORY_SEPARATOR.'semantic.bin', $set->forGroups(['guest']));
        }
    }

    /**
     * @param  bool  $rootRelative  Emit /-rooted asset paths instead of ../ ones.
     *                              A 404 page is served for URLs at any depth,
     *                              so relative paths in it resolve wrongly.
     */
    private function rewriteHtml(string $html, string $out, string $htmlPath, string $prefix, string $baseUrl, bool $rootRelative = false): string
    {
        // The canonical and og:url name the page for crawlers and have to stay
        // absolute, matching the sitemap. Set them aside while every other
        // link is made relative.
        $kept = [];
        $html = preg_replace_callback(
            '#(<link rel="canonical" href="|<meta property="og:url" content=")([^"]*)"#',
            static function (array $match) use (&$kept): string {
                $kept[] = $match[2];

                return $match[1].'VELLUMKEPT'.(count($kept) - 1).'_"';
            },
            $html,
        ) ?? $html;

        $appUrl = rtrim((string) config('app.url', ''), '/');

        // Only where app.url starts an attribute or url() value and is the
        // whole origin, so https://example.com.au and prose are left alone.
        if ($appUrl !== '') {
            $html = preg_replace('#(?<=["\'(])'.preg_quote($appUrl, '#').'(?=[/"\'?\#)])#', '', $html) ?? $html;
        }

        $relativeRoot = $rootRelative
            ? $this->normalizeBase($baseUrl)
            : $this->relativePrefix($htmlPath, $out);
        $vendorPrefix = $relativeRoot.'vendor/vellum/';
        $docsPrefix = $relativeRoot.($prefix !== '' ? $prefix.'/' : '');

        $html = preg_replace('#(?<=["\'\(])/vendor/vellum/#', $vendorPrefix, $html) ?? $html;

        if ($prefix !== '') {
            $html = preg_replace(
                '#(?<=["\'\(])/'.preg_quote($prefix, '#').'/#',
                $docsPrefix,
                $html,
            ) ?? $html;
            $html = preg_replace(
                '#(?<=["\'\(])/'.preg_quote($prefix, '#').'(?=["\'\)\?\#])#',
                rtrim($docsPrefix, '/'),
                $html,
            ) ?? $html;
        }

        // Optional absolute base for hosts that cannot resolve relative URLs.
        $normalizedBase = $this->normalizeBase($baseUrl);

        if (! $rootRelative && $normalizedBase !== '/') {
            $base = rtrim($normalizedBase, '/');
            $html = str_replace($vendorPrefix, $base.'/vendor/vellum/', $html);

            if ($prefix !== '') {
                $html = str_replace($docsPrefix, $base.'/'.$prefix.'/', $html);
            }
        }

        return preg_replace_callback(
            '#VELLUMKEPT(\d+)_#',
            static fn (array $match): string => $kept[(int) $match[1]] ?? '',
            $html,
        ) ?? $html;
    }

    /**
     * Relative path prefix from an exported HTML file up to the export root.
     */
    private function relativePrefix(string $htmlPath, string $out): string
    {
        $htmlPath = str_replace('\\', '/', $htmlPath);
        $out = rtrim(str_replace('\\', '/', $out), '/');
        $relative = trim(substr($htmlPath, strlen($out)), '/');
        $segments = $relative === '' ? [] : explode('/', $relative);
        $depth = max(0, count($segments) - 1);

        return $depth === 0 ? './' : str_repeat('../', $depth);
    }

    /**
     * Relative href from one exported file to another.
     */
    private function hrefBetween(string $fromFile, string $toFile, string $out): string
    {
        $fromDir = str_replace('\\', '/', dirname($fromFile));
        $toFile = str_replace('\\', '/', $toFile);
        $out = rtrim(str_replace('\\', '/', $out), '/');

        $fromRel = trim(substr($fromDir, strlen($out)), '/');
        $toRel = trim(substr($toFile, strlen($out)), '/');

        $fromParts = $fromRel === '' ? [] : explode('/', $fromRel);
        $toParts = $toRel === '' ? [] : explode('/', $toRel);

        if ($toParts !== [] && end($toParts) === 'index.html') {
            array_pop($toParts);
        }

        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $up = str_repeat('../', count($fromParts));
        $down = implode('/', $toParts);

        if ($up === '' && $down === '') {
            return './';
        }

        if ($up === '') {
            return './'.$down.'/';
        }

        if ($down === '') {
            return $up;
        }

        return $up.$down.'/';
    }

    private function normalizeBase(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);

        if ($baseUrl === '' || $baseUrl === '/') {
            return '/';
        }

        if (preg_match('#^https?://#i', $baseUrl) === 1) {
            $path = parse_url($baseUrl, PHP_URL_PATH);

            return is_string($path) && $path !== '' ? $path : '/';
        }

        return '/'.trim($baseUrl, '/').'/';
    }

    /**
     * A static host cannot generate a sitemap, so write one next to the pages.
     *
     * It goes at the export root rather than under the docs prefix, because
     * that root is the site root once the export is deployed.
     */
    private function writeSitemap(ContentRepository $repository, string $out): void
    {
        $entries = Sitemap::entries($repository, staticExport: true);

        if ($entries === []) {
            $this->warn('Skipped sitemap.xml: set app.url or vellum.export.base_url to an origin.');

            return;
        }

        $this->writeFile($out.DIRECTORY_SEPARATOR.'sitemap.xml', Sitemap::render($entries));
    }

    /**
     * llms.txt and llms-full.txt at the export root, next to the sitemap.
     * Without an origin the links stay root-relative, which Markdown allows.
     */
    private function writeLlmsTxt(ContentRepository $repository, string $out): void
    {
        if (! LlmsTxt::enabled()) {
            return;
        }

        $this->writeFile($out.DIRECTORY_SEPARATOR.'llms.txt', LlmsTxt::index($repository, staticExport: true));
        $this->writeFile($out.DIRECTORY_SEPARATOR.'llms-full.txt', LlmsTxt::full($repository, staticExport: true));
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create directory: '.$directory);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write file: '.$path);
        }

        $this->written[$path] = true;
    }

    /**
     * Delete what the last export wrote and this one did not: a page that was
     * deleted, renamed or gated since must not stay published. Only files
     * named in the last export's manifest are touched, never anything else in
     * the directory.
     */
    private function removeStaleFiles(string $out): void
    {
        $root = rtrim(str_replace('\\', '/', $out), '/');
        $manifest = $root.'/'.self::MANIFEST;
        $current = [];

        foreach (array_keys($this->written) as $path) {
            $current[ltrim(substr(str_replace('\\', '/', $path), strlen($root)), '/')] = true;
        }

        $previous = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

        foreach (is_array($previous) ? $previous : [] as $relative) {
            if (! is_string($relative) || isset($current[$relative]) || $relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $path = $root.'/'.$relative;

            if (is_file($path)) {
                unlink($path);
                $this->removeEmptyParents(dirname($path), $root);
            }
        }

        $list = array_keys($current);
        sort($list);
        file_put_contents($manifest, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function removeEmptyParents(string $directory, string $root): void
    {
        while ($directory !== $root && str_starts_with($directory, $root.'/') && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            rmdir($directory);
            $directory = dirname($directory);
        }
    }
}
