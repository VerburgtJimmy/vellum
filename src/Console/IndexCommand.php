<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Vellum\Answers\AnswersBuild;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Search\ScoutIndexer;
use Vellum\Search\SearchDriver;

/**
 * Rebuild what search reads: the answer index and the semantic set, and Scout
 * when that driver is on. vellum:build does this too; this is for when the
 * pages are already compiled and only the index is stale.
 */
final class IndexCommand extends Command
{
    protected $signature = 'vellum:index {--docs-version= : Index a single version folder}';

    protected $description = 'Rebuild the Vellum search index';

    public function handle(): int
    {
        $repository = ContentRepository::fromConfig();
        $version = $this->option('docs-version');
        $version = is_string($version) && $version !== '' ? $version : null;

        $documents = $repository->buildAll($version);

        if (SearchDriver::isScout()) {
            SearchDriver::assertScoutInstalled();
            // Scout is replaced whole, so it gets every version even when
            // only one was asked for.
            (new ScoutIndexer)->sync($repository, $version === null ? $documents : $repository->buildAll());
            $this->info('Scout index updated ('.$this->count($documents).' documents).');
        }

        (new AnswersBuild($this->line(...), $this->warn(...)))->run($repository, $documents, prune: $version === null);

        $this->info('Search index rebuilt for '.$this->count($documents).' document(s).');

        return self::SUCCESS;
    }

    /**
     * @param  list<Document>  $documents
     */
    private function count(array $documents): int
    {
        return count($documents);
    }
}
