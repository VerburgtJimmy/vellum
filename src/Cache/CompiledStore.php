<?php

declare(strict_types=1);

namespace Vellum\Cache;

use Vellum\Content\Document;
use Vellum\Markdown\Islands\Island;
use Vellum\Support\Slug;

/**
 * Reads and writes compiled documents as OPcache-friendly PHP return files.
 *
 * @phpstan-type NavTree list<array<string, mixed>>
 * @phpstan-type Manifest array{
 *     directory_hash: string,
 *     version: string|null,
 *     complete?: bool
 * }
 */
final class CompiledStore
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * Absolute path for a compiled document PHP file.
     */
    public function pathFor(string $slug, ?string $version = null): string
    {
        $slug = trim($slug, '/');

        if (! Slug::isSafe($slug)) {
            throw new \InvalidArgumentException('Refusing to resolve a compiled path for unsafe slug: '.$slug);
        }

        $file = ($slug === '' ? 'index' : $slug).'.php';

        return $this->pagesPath($version).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
    }

    /**
     * Compiled pages live apart from the sidebar, manifest and search index,
     * so a page called nav.md or manifest.md cannot overwrite them.
     */
    public function pagesPath(?string $version = null): string
    {
        return $this->versionPath($version).DIRECTORY_SEPARATOR.'pages';
    }

    /**
     * Delete compiled pages a build did not produce: a page whose file was
     * deleted, renamed or given a new slug must stop being served.
     *
     * @param  list<string>  $slugs
     */
    public function prunePages(array $slugs, ?string $version = null): void
    {
        $root = $this->pagesPath($version);

        if (! is_dir($root)) {
            return;
        }

        $keep = [];

        foreach ($slugs as $slug) {
            $keep[$this->pathFor($slug, $version)] = true;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } elseif (! isset($keep[$file->getPathname()])) {
                unlink($file->getPathname());
            }
        }
    }

    public function versionPath(?string $version = null): string
    {
        if ($version === null || $version === '') {
            return rtrim($this->basePath, DIRECTORY_SEPARATOR);
        }

        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$version;
    }

    public function exists(string $slug, ?string $version = null): bool
    {
        return is_file($this->pathFor($slug, $version));
    }

    public function get(string $slug, ?string $version = null): ?Document
    {
        $path = $this->pathFor($slug, $version);

        if (! is_file($path)) {
            return null;
        }

        $data = require $path;

        if (! is_array($data) || ! isset($data['slug'], $data['title'], $data['html'], $data['path'], $data['mtime'])) {
            return null;
        }

        if (! is_string($data['slug']) || ! is_string($data['title']) || ! is_string($data['html']) || ! is_string($data['path']) || ! is_int($data['mtime'])) {
            return null;
        }

        $headings = [];

        if (isset($data['headings']) && is_array($data['headings'])) {
            foreach ($data['headings'] as $heading) {
                if (
                    is_array($heading)
                    && isset($heading['id'], $heading['text'], $heading['level'])
                    && is_string($heading['id'])
                    && is_string($heading['text'])
                    && is_int($heading['level'])
                ) {
                    $headings[] = [
                        'id' => $heading['id'],
                        'text' => $heading['text'],
                        'level' => $heading['level'],
                    ];
                }
            }
        }

        $frontmatter = [];

        if (isset($data['frontmatter']) && is_array($data['frontmatter'])) {
            foreach ($data['frontmatter'] as $key => $value) {
                if (is_string($key)) {
                    $frontmatter[$key] = $value;
                }
            }
        }

        return new Document(
            slug: $data['slug'],
            title: $data['title'],
            html: $data['html'],
            headings: $headings,
            frontmatter: $frontmatter,
            path: $data['path'],
            mtime: $data['mtime'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            version: isset($data['version']) && is_string($data['version']) ? $data['version'] : null,
            full: isset($data['full']) && is_bool($data['full']) ? $data['full'] : false,
            icon: isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : null,
            islands: Island::listFromArray($data['islands'] ?? []),
            updated: isset($data['updated']) && is_string($data['updated']) ? $data['updated'] : null,
        );
    }

    public function put(Document $document): void
    {
        $path = $this->pathFor($document->slug, $document->version);
        $this->writePhpReturn($path, $document->toArray());
    }

    /**
     * @param  NavTree  $tree
     */
    public function putNav(array $tree, ?string $version = null): void
    {
        $this->writePhpReturn($this->navPath($version), $tree);
    }

    /**
     * @return NavTree|null
     */
    public function getNav(?string $version = null): ?array
    {
        $path = $this->navPath($version);

        if (! is_file($path)) {
            return null;
        }

        $data = require $path;

        if (! is_array($data)) {
            return null;
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values($data);

        return $list;
    }

    public function navPath(?string $version = null): string
    {
        return $this->versionPath($version).DIRECTORY_SEPARATOR.'nav.php';
    }

    /**
     * @param  Manifest  $manifest
     */
    public function putManifest(array $manifest, ?string $version = null): void
    {
        $this->writePhpReturn($this->manifestPath($version), $manifest);
    }

    /**
     * @return Manifest|null
     */
    public function getManifest(?string $version = null): ?array
    {
        $path = $this->manifestPath($version);

        if (! is_file($path)) {
            return null;
        }

        $data = require $path;

        if (! is_array($data)) {
            return null;
        }

        if (! isset($data['directory_hash']) || ! is_string($data['directory_hash'])) {
            return null;
        }

        return [
            'directory_hash' => $data['directory_hash'],
            'version' => isset($data['version']) && is_string($data['version']) ? $data['version'] : null,
            'complete' => ($data['complete'] ?? false) === true,
        ];
    }

    public function manifestPath(?string $version = null): string
    {
        return $this->versionPath($version).DIRECTORY_SEPARATOR.'manifest.php';
    }

    /**
     * Delete every compiled file under the cache root.
     */
    public function clear(): void
    {
        if (! is_dir($this->basePath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function writePhpReturn(string $path, array $data): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create compiled cache directory [{$directory}]");
        }

        $export = var_export($data, true);
        $contents = <<<PHP
<?php

declare(strict_types=1);

return {$export};

PHP;

        if (! self::write($path, $contents)) {
            throw new \RuntimeException("Unable to write compiled file [{$path}]");
        }
    }

    /**
     * Write through a temporary file and rename it into place. Production
     * compiles on a cache miss while other requests may be reading the same
     * file, and a rename means a reader sees the old file or the new one,
     * never half of either.
     */
    private static function write(string $path, string $contents): bool
    {
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if (file_put_contents($temporary, $contents) === false) {
            return false;
        }

        if (! rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }

        if (str_ends_with($path, '.php') && function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        return true;
    }
}
