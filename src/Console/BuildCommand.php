<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Vellum\Content\ContentRepository;

/**
 * Compiles every Markdown document into the OPcache-friendly cache.
 */
final class BuildCommand extends Command
{
    protected $signature = 'vellum:build {--docs-version= : Compile a single version folder}';

    protected $description = 'Compile Markdown docs into the Vellum cache';

    public function handle(): int
    {
        $started = microtime(true);
        $repository = ContentRepository::fromConfig();
        $version = $this->option('docs-version');
        $version = is_string($version) && $version !== '' ? $version : null;

        $documents = $repository->buildAll($version);
        $elapsed = round((microtime(true) - $started) * 1000);

        $this->info(sprintf(
            'Compiled %d document%s in %d ms',
            count($documents),
            count($documents) === 1 ? '' : 's',
            $elapsed,
        ));

        foreach ($documents as $document) {
            $label = $document->version !== null
                ? "{$document->version}/{$document->slug}"
                : $document->slug;

            $this->line('  - '.($label === '' ? '/' : $label));
        }

        $store = $repository->store();
        $versions = $version !== null
            ? [$version]
            : ($repository->versionsEnabled() ? $repository->versions() : [null]);

        foreach ($versions as $ver) {
            $navPath = $store->navPath($ver);
            $manifest = $store->getManifest($ver);

            if (is_file($navPath)) {
                $label = $ver ?? 'default';
                $this->line("  nav: {$label}");
            }

            if ($manifest !== null) {
                $this->line('  search hash: '.$manifest['search_hash']);
            }
        }

        return self::SUCCESS;
    }
}
