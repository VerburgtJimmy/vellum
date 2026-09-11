<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\File;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\HeadingExtractor;

/**
 * Renders the docs site to a static HTML folder for any static host.
 */
final class ExportCommand extends Command
{
    protected $signature = 'vellum:export {--out= : Output directory (defaults to config vellum.export.out)}';

    protected $description = 'Export documentation to a static HTML folder';

    public function __construct(
        private readonly ViewFactory $view,
        private readonly HeadingExtractor $headingExtractor = new HeadingExtractor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $started = microtime(true);
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
            $html = $this->renderDocument($repository, $document);
            $html = $this->rewriteHtml($html, $baseUrl, $prefix);
            $path = $this->documentOutputPath($prefixRoot, $document);

            $this->writeFile($path, $html);
            $pages++;
        }

        if ($repository->versionsEnabled()) {
            $latest = $repository->latestVersion();

            if ($latest !== null) {
                foreach ($documents as $document) {
                    if ($document->version !== $latest) {
                        continue;
                    }

                    $targetHref = $this->joinBase($baseUrl, $repository->hrefFor($document->slug, $latest));
                    $redirectHtml = $this->redirectHtml($targetHref);
                    $path = $this->unversionedOutputPath($prefixRoot, $document->slug);
                    $this->writeFile($path, $redirectHtml);
                    $pages++;
                }
            }
        }

        $this->copyDist($packageRoot, $out);
        $this->copyContentFiles((string) config('vellum.path'), $prefixRoot);
        $this->writeSearchIndexes($repository, $prefixRoot);

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
        $navigation = $repository->navigation($document->version);
        $adjacent = $repository->adjacent($document->slug, $document->version);
        $breadcrumbs = $repository->breadcrumbs($document);
        $toc = $this->headingExtractor->nest($document->headings);
        $switcher = $repository->versionSwitcherData($document->slug, $document->version);

        return $this->view->file(
            dirname(__DIR__, 2).'/resources/views/pages/doc.blade.php',
            [
                'document' => $document,
                'name' => config('vellum.name'),
                'description' => $document->description,
                'navigation' => $navigation,
                'previous' => $adjacent['previous'],
                'next' => $adjacent['next'],
                'breadcrumbs' => $breadcrumbs,
                'toc' => $toc,
                'searchHash' => $repository->searchHash($document->version),
                'versions' => $switcher['versions'],
                'currentVersion' => $switcher['currentVersion'],
                'versionHrefs' => $switcher['versionHrefs'],
            ],
        )->render();
    }

    private function documentOutputPath(string $prefixRoot, Document $document): string
    {
        $segments = [];

        if ($document->version !== null && $document->version !== '') {
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

    private function unversionedOutputPath(string $prefixRoot, string $slug): string
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return $prefixRoot.DIRECTORY_SEPARATOR.'index.html';
        }

        return $prefixRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $slug).DIRECTORY_SEPARATOR.'index.html';
    }

    private function redirectHtml(string $targetHref): string
    {
        $escaped = htmlspecialchars($targetHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={$escaped}">
    <link rel="canonical" href="{$escaped}">
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

            $name = $file->getFilename();
            $extension = strtolower($file->getExtension());

            if ($extension === 'md' || $name === 'meta.json') {
                continue;
            }

            $absolute = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($absolute, strlen($contentPath)), '/');

            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $destination = $filesRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $directory = dirname($destination);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            copy($file->getPathname(), $destination);
        }
    }

    private function writeSearchIndexes(ContentRepository $repository, string $prefixRoot): void
    {
        $searchDir = $prefixRoot.DIRECTORY_SEPARATOR.'_vellum';

        if (! is_dir($searchDir)) {
            mkdir($searchDir, 0755, true);
        }

        $store = $repository->store();
        $versionKeys = $repository->versionsEnabled()
            ? $repository->versions()
            : [null];

        $latestJson = null;

        foreach ($versionKeys as $version) {
            $hash = $repository->searchHash($version);
            $json = $store->getSearchIndex($version, $hash);

            if ($json === null) {
                continue;
            }

            if ($hash !== null && $hash !== '') {
                $this->writeFile($searchDir.DIRECTORY_SEPARATOR.'search-'.$hash.'.json', $json);
            }

            if (! $repository->versionsEnabled() || $version === $repository->latestVersion()) {
                $latestJson = $json;
            }
        }

        if ($latestJson !== null) {
            $this->writeFile($searchDir.DIRECTORY_SEPARATOR.'search.json', $latestJson);
        }
    }

    private function rewriteHtml(string $html, string $baseUrl, string $prefix): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');

        if ($appUrl !== '') {
            $html = str_replace($appUrl, '', $html);
        }

        $normalizedBase = $this->normalizeBase($baseUrl);

        if ($normalizedBase === '/') {
            return $html;
        }

        $base = rtrim($normalizedBase, '/');

        $html = preg_replace('#(?<=["\'\(])/vendor/vellum/#', $base.'/vendor/vellum/', $html) ?? $html;
        $html = preg_replace(
            '#(?<=["\'\(])/'.preg_quote($prefix, '#').'/#',
            $base.'/'.$prefix.'/',
            $html,
        ) ?? $html;
        $html = preg_replace(
            '#(?<=["\'\(])/'.preg_quote($prefix, '#').'(?=["\'\)])#',
            $base.'/'.$prefix,
            $html,
        ) ?? $html;

        return $html;
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

    private function joinBase(string $baseUrl, string $path): string
    {
        $normalizedBase = $this->normalizeBase($baseUrl);
        $path = '/'.ltrim($path, '/');

        if ($normalizedBase === '/') {
            return $path;
        }

        return rtrim($normalizedBase, '/').$path;
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
    }
}
