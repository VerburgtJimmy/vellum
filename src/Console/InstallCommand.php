<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Publishes config, starter Markdown stubs, and public dist assets.
 */
final class InstallCommand extends Command
{
    protected $signature = 'vellum:install {--force : Overwrite the published config and existing stub files}';

    protected $description = 'Install Vellum config, docs stubs, and public assets';

    public function handle(): int
    {
        $packageRoot = dirname(__DIR__, 2);
        $force = (bool) $this->option('force');

        $this->publishConfig($packageRoot, $force);
        $this->copyStubs($packageRoot, $force);
        $this->copyDist($packageRoot);

        $this->newLine();
        $this->info('Vellum installed successfully.');
        $this->line('  Next: edit your docs under '.config('vellum.path'));
        $this->line('  Then open /'.trim((string) config('vellum.route.prefix', 'docs'), '/'));

        return self::SUCCESS;
    }

    private function publishConfig(string $packageRoot, bool $force): void
    {
        $target = config_path('vellum.php');

        if (is_file($target) && ! $force) {
            $this->line('Config already exists: '.$target.' (use --force to overwrite)');

            return;
        }

        $source = $packageRoot.'/config/vellum.php';

        if (! is_file($source)) {
            $this->error('Package config missing: '.$source);

            return;
        }

        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! copy($source, $target)) {
            $this->error('Unable to publish config to '.$target);

            return;
        }

        $this->info('Published config: '.$target);
    }

    private function copyStubs(string $packageRoot, bool $force): void
    {
        $source = $packageRoot.'/resources/stubs';
        $target = (string) config('vellum.path', resource_path('docs'));

        if (! is_dir($source)) {
            $this->error('Package stubs missing: '.$source);

            return;
        }

        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $copied = 0;
        $skipped = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = substr($file->getPathname(), strlen($source) + 1);
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if ($file->isDir()) {
                if (! is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }

                continue;
            }

            if (is_file($destination) && ! $force) {
                $skipped++;

                continue;
            }

            $directory = dirname($destination);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if (! copy($file->getPathname(), $destination)) {
                $this->warn('Unable to copy stub: '.$relative);

                continue;
            }

            $copied++;
        }

        $this->info(sprintf(
            'Copied %d stub file%s to %s%s',
            $copied,
            $copied === 1 ? '' : 's',
            $target,
            $skipped > 0 ? " ({$skipped} skipped)" : '',
        ));
    }

    private function copyDist(string $packageRoot): void
    {
        $source = $packageRoot.'/resources/dist';
        $target = public_path('vendor/vellum');

        if (! is_dir($source)) {
            $this->error('Package dist missing: '.$source);

            return;
        }

        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $copied = 0;

        foreach (File::files($source) as $file) {
            $destination = $target.DIRECTORY_SEPARATOR.$file->getFilename();

            if (! copy($file->getPathname(), $destination)) {
                $this->warn('Unable to copy asset: '.$file->getFilename());

                continue;
            }

            $copied++;
        }

        $this->info(sprintf(
            'Copied %d asset%s to %s',
            $copied,
            $copied === 1 ? '' : 's',
            $target,
        ));
    }
}
