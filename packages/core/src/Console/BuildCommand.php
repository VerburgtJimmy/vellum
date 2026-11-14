<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Vellum\Answers\AnswersBuild;
use Vellum\Answers\SearchCheck;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\LastUpdated;
use Vellum\Content\LinkChecker;
use Vellum\Search\ScoutIndexer;
use Vellum\Search\SearchDriver;

/**
 * Compiles every Markdown document into the OPcache-friendly cache.
 */
final class BuildCommand extends Command
{
    protected $signature = 'vellum:build
        {--docs-version= : Compile a single version folder}
        {--strict : Fail the build when a link or image points at nothing}
        {--check-search : Ask the questions in questions.yml even when checks.search is off}';

    protected $description = 'Compile Markdown docs into the Vellum cache';

    public function handle(ContentRepository $repository): int
    {
        $started = microtime(true);

        $version = $this->option('docs-version');
        $version = is_string($version) && $version !== '' ? $version : null;

        $documents = $repository->buildAll($version);

        foreach ($repository->generatedWarnings() as $warning) {
            $this->warn('OpenAPI: '.$warning);
        }

        // Core alone does not have the reference, and an app that turns it on
        // should hear what to install rather than find no reference.
        if ((bool) ($repository->site()->get('openapi')['enabled'] ?? false) && ! in_array('api', $repository->sourceNames(), true)) {
            $this->warn('vellum.openapi.enabled is on, but the API reference is not installed.');
            $this->line('  Run composer require jimmyverburgt/vellum-openapi to add it.');
        }

        $this->warnAboutShadowedSlugs($documents, $repository);
        $this->warnAboutInvalidDates($documents);

        $broken = $this->reportBrokenReferences($documents, $repository);
        $misplaced = $this->reportMisplacedRoots($repository);

        if ($repository->site()->search->isScout()) {
            SearchDriver::assertScoutInstalled();
            // Scout is replaced whole, so it gets every version even when
            // only one was asked for.
            (new ScoutIndexer)->sync($repository, $version === null ? $documents : $repository->buildAll());
        }

        $searchFailures = (new AnswersBuild(
            $this->line(...),
            $this->warn(...),
            forceCheck: (bool) $this->option('check-search'),
            strict: $this->strict($repository),
        ))->run($repository, $documents);

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

            if (is_file($navPath)) {
                $label = $ver ?? 'default';
                $this->line("  nav: {$label}");
            }
        }

        if ($searchFailures > 0) {
            $this->error('Search did not answer the questions '.SearchCheck::FILE.' asks of it.');

            return self::FAILURE;
        }

        if (($broken > 0 || $misplaced > 0) && $this->strict($repository)) {
            $problems = array_filter([
                $broken > 0 ? sprintf('%d broken reference%s', $broken, $broken === 1 ? '' : 's') : null,
                $misplaced > 0 ? sprintf('%d misplaced root%s', $misplaced, $misplaced === 1 ? '' : 's') : null,
            ]);

            $this->error(implode(' and ', $problems).'. Fix them, or drop --strict to let the build pass.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Strict is a flag for a one-off run and a config key for CI, which does
     * not get to pass flags to whatever the deploy script calls.
     */
    private function strict(ContentRepository $repository): bool
    {
        return (bool) $this->option('strict') || $repository->site()->checks->strict;
    }

    /**
     * The package registers /changelog ahead of the catch-all docs route, so a
     * page of the same name never gets served. Say so rather than let the
     * author wonder why their edits do nothing.
     *
     * @param  list<Document>  $documents
     */
    /**
     * Returns the count that --strict acts on, which is errors only: a
     * heading that skips a level is worth saying and not worth failing on.
     *
     * @param  list<Document>  $documents
     */
    private function reportBrokenReferences(array $documents, ContentRepository $repository): int
    {
        if (! $repository->site()->checks->references) {
            return 0;
        }

        $findings = (new LinkChecker)->check($documents, $repository);
        $errors = 0;

        foreach ($findings as $finding) {
            $notice = $finding['severity'] === 'notice';

            $this->warn(sprintf(
                '%s %s on %s: %s (%s)',
                $notice ? 'Check' : 'Broken',
                $finding['kind'],
                $finding['page'] === '' ? '/' : $finding['page'],
                $finding['target'],
                $finding['message'],
            ));

            $errors += $notice ? 0 : 1;
        }

        return $errors;
    }

    /**
     * An `updated` that is not a date is ignored, and the page falls back to
     * git or to no date. The page still renders, so say so here rather than
     * let the date go missing without a word.
     *
     * @param  list<Document>  $documents
     */
    private function warnAboutInvalidDates(array $documents): void
    {
        foreach ($documents as $document) {
            if (! array_key_exists('updated', $document->frontmatter) || LastUpdated::fromMatter($document->frontmatter['updated']) !== null) {
                continue;
            }

            $value = $document->frontmatter['updated'];

            $this->warn(sprintf(
                'Invalid updated date in %s: %s',
                $document->path,
                is_scalar($value) ? (string) $value : get_debug_type($value),
            ));
            $this->line('  Use YYYY-MM-DD or an ISO 8601 date and time. Ignored for now.');
        }
    }

    /**
     * A sidebar of its own can only be a top-level folder's. Counted with the
     * broken references, so --strict fails on it the same way.
     */
    private function reportMisplacedRoots(ContentRepository $repository): int
    {
        $misplaced = $repository->misplacedRoots();

        foreach ($misplaced as $folder) {
            $this->warn('Folder "'.$folder.'" sets root in its meta.json, but only a top-level folder can have a sidebar of its own.');
            $this->line('  It is shown as an ordinary folder. Move it to the top level, or remove root.');
        }

        return count($misplaced);
    }

    /**
     * @param  list<Document>  $documents
     */
    private function warnAboutShadowedSlugs(array $documents, ContentRepository $repository): void
    {
        if ($repository->site()->changelogPath === null) {
            return;
        }

        foreach ($documents as $document) {
            if ($document->slug !== 'changelog') {
                continue;
            }

            $this->warn('Page "'.$document->path.'" is shadowed by the changelog route.');
            $this->line('  /'.$repository->site()->routePrefix.'/changelog renders vellum.changelog.path instead.');
            $this->line('  Rename the page, or set vellum.changelog.path to null.');
        }
    }
}
