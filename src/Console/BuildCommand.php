<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Search\ScoutIndexer;
use Vellum\Search\SearchDriver;
use Vellum\Support\Theme;

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

        $removed = Theme::removedPreset();

        if ($removed !== null) {
            $this->warn(sprintf('Colour preset "%s" was removed in 0.5; using neutral.', $removed));
            $this->line('  Set vellum.theme.preset to one of: '.implode(', ', Theme::PRESETS));
        }

        $repository = ContentRepository::fromConfig();
        $version = $this->option('docs-version');
        $version = is_string($version) && $version !== '' ? $version : null;

        $documents = $repository->buildAll($version);

        $this->warnAboutShadowedSlugs($documents);

        if (SearchDriver::isScout()) {
            SearchDriver::assertScoutInstalled();
            (new ScoutIndexer)->sync($repository, $documents);
        }

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

    /**
     * The package registers /changelog ahead of the catch-all docs route, so a
     * page of the same name never gets served. Say so rather than let the
     * author wonder why their edits do nothing.
     *
     * @param  list<Document>  $documents
     */
    private function warnAboutShadowedSlugs(array $documents): void
    {
        if (config('vellum.changelog.path') === null) {
            return;
        }

        foreach ($documents as $document) {
            if ($document->slug !== 'changelog') {
                continue;
            }

            $this->warn('Page "'.$document->path.'" is shadowed by the changelog route.');
            $this->line('  /'.trim((string) config('vellum.route.prefix', 'docs'), '/').'/changelog renders vellum.changelog.path instead.');
            $this->line('  Rename the page, or set vellum.changelog.path to null.');
        }
    }
}
