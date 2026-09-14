<?php

declare(strict_types=1);

namespace Vellum\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase as Orchestra;
use Vellum\VellumServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * When set, overrides Testbench's forced "testing" environment for the next boot.
     */
    protected ?string $forceAppEnvironment = null;

    protected function setUp(): void
    {
        parent::setUp();

        $docs = $this->docsPath();
        $cache = $this->cachePath();

        if (! is_dir($docs)) {
            mkdir($docs, 0755, true);
        }

        if (! is_dir($cache)) {
            mkdir($cache, 0755, true);
        }

        config()->set('vellum.path', $docs);
        config()->set('vellum.cache.path', $cache);
        config()->set('vellum.versions.enabled', false);
        config()->set('cache.default', 'array');
        config()->set('vellum.changelog', null);
    }

    /**
     * @param  Application  $app
     */
    protected function resolveApplicationCore($app): void
    {
        if ($this->forceAppEnvironment !== null) {
            $app->detectEnvironment(fn (): string => $this->forceAppEnvironment);

            return;
        }

        parent::resolveApplicationCore($app);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->docsPath());
        $this->deleteDirectory($this->cachePath());

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            VellumServiceProvider::class,
        ];
    }

    protected function docsPath(): string
    {
        return sys_get_temp_dir().'/vellum-tests/docs-'.$this->fixtureId();
    }

    protected function cachePath(): string
    {
        return sys_get_temp_dir().'/vellum-tests/cache-'.$this->fixtureId();
    }

    protected function fixtureId(): string
    {
        return md5(static::class.$this->name());
    }

    /**
     * Host Markdown components under tests/fixtures/components.
     * realpath() so every test file registers the same Blade anonymous namespace.
     */
    protected function registerFixtureComponents(): void
    {
        $path = realpath(__DIR__.'/fixtures/components');

        if ($path === false) {
            throw new \RuntimeException('Fixture components directory is missing.');
        }

        Blade::anonymousComponentPath($path);
    }

    protected function writeDoc(string $relativePath, string $contents): string
    {
        $path = $this->docsPath().'/'.ltrim(str_replace('\\', '/', $relativePath), '/');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
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

        rmdir($directory);
    }
}
