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
use Vellum\Support\Theme;

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
        $this->warnAboutInvalidDates($documents);

        $broken = $this->reportBrokenReferences($documents, $repository);

        if (SearchDriver::isScout()) {
            SearchDriver::assertScoutInstalled();
            (new ScoutIndexer)->sync($repository, $documents);
        }

        $searchFailures = (new AnswersBuild(
            $this->line(...),
            $this->warn(...),
            forceCheck: (bool) $this->option('check-search'),
            strict: $this->strict(),
        ))->run(
            $repository,
            $documents,
            prune: $this->option('docs-version') === null,
        );

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

        if ($broken > 0 && $this->strict()) {
            $this->error(sprintf(
                '%d broken reference%s. Fix them, or drop --strict to let the build pass.',
                $broken,
                $broken === 1 ? '' : 's',
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Strict is a flag for a one-off run and a config key for CI, which does
     * not get to pass flags to whatever the deploy script calls.
     */
    private function strict(): bool
    {
        return (bool) $this->option('strict') || (bool) config('vellum.checks.strict', false);
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
        if (! (bool) config('vellum.checks.references', true)) {
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
