<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Illuminate\Support\Facades\Artisan;
use Vellum\Exceptions\InvalidSpecException;

/**
 * Decides which OpenAPI document to render, in the order a project expects.
 *
 * A configured file wins. Otherwise Scramble is asked to export one, since a
 * Laravel API that already generates a spec should not have to commit it.
 * Otherwise there is nothing to render and the build says so once.
 *
 * The bridge goes through Artisan rather than Scramble's classes. Its command
 * name and its --path option are public surface; everything behind them is not.
 */
final class SpecSource
{
    private const EXPORT_COMMAND = 'scramble:export';

    /** @var list<string> */
    private array $warnings = [];

    /**
     * Absolute path to a spec file, or null when there is nothing to render.
     */
    public function resolve(): ?string
    {
        $this->warnings = [];

        if (! self::enabled()) {
            return null;
        }

        $configured = config('vellum.openapi.spec');

        if (is_string($configured) && $configured !== '') {
            if (! is_file($configured)) {
                // Named a file and it is not there: a typo, not an absence.
                throw InvalidSpecException::unreadable($configured);
            }

            return $configured;
        }

        if ((bool) config('vellum.openapi.scramble', true)) {
            $exported = $this->exportFromScramble();

            if ($exported !== null) {
                return $exported;
            }
        }

        $this->warnings[] = 'OpenAPI is enabled but no spec was found. Set vellum.openapi.spec, or install dedoc/scramble.';

        return null;
    }

    /**
     * A cheap answer to "has what the pages are built from changed", worked
     * out without reading or exporting a spec.
     *
     * A spec file answers with its modification time. A spec Scramble writes
     * has no file to ask until it is exported, and exporting on every request
     * is the cost this exists to avoid, so the answer comes from the code
     * Scramble reads: the newest change under the watched directories.
     */
    public function signature(): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        $configured = config('vellum.openapi.spec');

        if (is_string($configured) && $configured !== '') {
            return is_file($configured)
                ? 'file:'.$configured.':'.filemtime($configured).':'.filesize($configured)
                : 'file:'.$configured.':missing';
        }

        if ((bool) config('vellum.openapi.scramble', true) && self::scrambleAvailable()) {
            return 'scramble:'.$this->watchedState();
        }

        return null;
    }

    /**
     * Newest modification time and file count under the watched directories.
     * The count catches a deleted controller, which changes no file's time.
     */
    private function watchedState(): string
    {
        $newest = 0;
        $count = 0;

        foreach (self::watched() as $directory) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $newest = max($newest, (int) $file->getMTime());
                    $count++;
                }
            }
        }

        return $newest.':'.$count;
    }

    /**
     * @return list<string>
     */
    private static function watched(): array
    {
        $configured = config('vellum.openapi.watch');
        $paths = is_array($configured) ? $configured : [base_path('app'), base_path('routes')];

        return array_values(array_filter($paths, static fn (mixed $path): bool => is_string($path) && is_dir($path)));
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public static function enabled(): bool
    {
        return (bool) config('vellum.openapi.enabled', false);
    }

    public static function scrambleAvailable(): bool
    {
        return array_key_exists(self::EXPORT_COMMAND, Artisan::all());
    }

    private function exportFromScramble(): ?string
    {
        if (! self::scrambleAvailable()) {
            return null;
        }

        $target = $this->targetPath();
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->warnings[] = "Unable to create [{$directory}] for the exported spec.";

            return null;
        }

        if (! $this->exportSupportsPath()) {
            // Without --path the file lands wherever Scramble defaults to, and
            // guessing is worse than saying so.
            $this->warnings[] = 'Installed scramble:export has no --path option. Set vellum.openapi.spec to the file it writes.';

            return null;
        }

        if (is_file($target)) {
            unlink($target);
        }

        $status = Artisan::call(self::EXPORT_COMMAND, ['--path' => $target]);

        if ($status !== 0 || ! is_file($target)) {
            $this->warnings[] = 'scramble:export did not produce a spec. Run it by hand to see why.';

            return null;
        }

        return $target;
    }

    private function exportSupportsPath(): bool
    {
        $command = Artisan::all()[self::EXPORT_COMMAND] ?? null;

        return $command !== null && $command->getDefinition()->hasOption('path');
    }

    private function targetPath(): string
    {
        $cache = config('vellum.cache.path');
        $directory = is_string($cache) && $cache !== '' ? $cache : app()->storagePath('framework/vellum');

        return rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'openapi.json';
    }
}
