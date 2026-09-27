<?php

declare(strict_types=1);

namespace Vellum\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
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
        $this->deleteDirectory($this->publishedViewsPath());

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

    /**
     * Views an app published to resources/views/vendor/vellum, keyed by their
     * path under it. The view services are rebuilt, so the providers resolve
     * the vellum namespace again with these in place.
     *
     * @param  array<string, string>  $views
     */
    protected function publishViews(array $views): void
    {
        foreach ($views as $relativePath => $contents) {
            $path = $this->publishedViewsPath().'/vendor/vellum/'.$relativePath;

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $contents);
        }

        config()->set('view.paths', [$this->publishedViewsPath()]);
        $this->app->forgetInstance('view.finder');
        $this->app->forgetInstance('view');
        Facade::clearResolvedInstances();
    }

    protected function publishedViewsPath(): string
    {
        return sys_get_temp_dir().'/vellum-tests/views-'.$this->fixtureId();
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

    /**
     * Run git in a fixture repository with a fixed identity and date, and
     * without any GIT_DIR the test run itself inherited.
     *
     * @param  list<string>  $arguments
     */
    protected function git(string $cwd, array $arguments, string $date = '2020-01-02T03:04:05+00:00'): void
    {
        (new Process(['git', ...$arguments], $cwd, [
            'GIT_DIR' => false,
            'GIT_WORK_TREE' => false,
            'GIT_INDEX_FILE' => false,
            'GIT_AUTHOR_NAME' => 'Docs',
            'GIT_AUTHOR_EMAIL' => 'docs@example.com',
            'GIT_COMMITTER_NAME' => 'Docs',
            'GIT_COMMITTER_EMAIL' => 'docs@example.com',
            'GIT_AUTHOR_DATE' => $date,
            'GIT_COMMITTER_DATE' => $date,
        ]))->mustRun();
    }

    protected function commitAll(string $cwd, string $message, string $date): void
    {
        $this->git($cwd, ['add', '-A']);
        $this->git($cwd, ['-c', 'commit.gpgsign=false', 'commit', '-q', '-m', $message], $date);
    }

    protected function skipWithoutGit(): void
    {
        if ((new ExecutableFinder)->find('git') === null) {
            $this->markTestSkipped('git is not installed.');
        }
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
