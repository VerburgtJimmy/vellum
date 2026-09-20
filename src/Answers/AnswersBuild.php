<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Closure;
use Vellum\Answers\Llm\LlmQuestions;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;

/**
 * Builds what search reads, per docs version: the answer index, and the
 * semantic set when the model is there. Shared by vellum:build and
 * vellum:index, which only differ in what else they do.
 */
final class AnswersBuild
{
    /**
     * @param  Closure(string): void  $line
     * @param  Closure(string): void  $warn
     */
    public function __construct(
        private readonly Closure $line,
        private readonly Closure $warn,
    ) {}

    /**
     * @param  list<Document>  $documents
     * @param  bool  $prune  drop cached questions for sections that are gone,
     *                       which is only safe when every version was built
     */
    public function run(ContentRepository $repository, array $documents, bool $prune = true): void
    {
        if (! (bool) config('vellum.answers.enabled', true)) {
            return;
        }

        $byVersion = [];

        foreach ($documents as $document) {
            $byVersion[$document->version ?? ''][] = $document;
        }

        $semantic = (bool) config('vellum.answers.semantic', true);
        $llm = LlmQuestions::fromConfig();
        $warned = false;

        foreach ($byVersion as $version => $group) {
            $root = $repository->store()->versionPath($version === '' ? null : $version);
            $index = AnswerIndex::build($group, $repository);
            $suffix = $version === '' ? '' : " {$version}";

            if ($llm !== null) {
                $written = $llm->for($index, function (int $done, int $total) use ($suffix): void {
                    ($this->line)(sprintf('  written questions%s: %d of %d sections', $suffix, $done, $total));
                });

                $index = $index->withQuestions($written['questions']);

                ($this->line)(sprintf(
                    '  written questions%s: %d from the cache, %d written%s',
                    $suffix,
                    $written['cached'],
                    $written['written'],
                    match (true) {
                        $written['missing'] === 0 => '',
                        $written['stopped'] => sprintf(', %d sections not asked for', $written['missing']),
                        default => sprintf(', %d sections have none yet', $written['missing']),
                    },
                ));

                foreach (array_slice($written['failures'], 0, 3) as $failure) {
                    ($this->warn)('  '.$failure);
                }

                if (count($written['failures']) > 3) {
                    ($this->warn)(sprintf('  and %d more sections the model did not answer for', count($written['failures']) - 3));
                }

                if ($written['stopped']) {
                    ($this->warn)(sprintf('  Stopped asking after %d failures in a row. Fix the cause and build again; what was written is cached.', LlmQuestions::GIVE_UP_AFTER));
                }
            }

            $index->save($root.'/answers');

            ($this->line)(sprintf(
                '  answers%s: %d sections, %d questions',
                $suffix,
                count($index->sections),
                array_sum(array_map(static fn (array $record): int => count($record['questions']), $index->sections)),
            ));

            if (! $semantic) {
                continue;
            }

            $indexer = SemanticIndexer::fromConfig($root.'/semantic');

            if (! $indexer->hasModel()) {
                if (! $warned) {
                    ($this->warn)('No embedding model in '.$indexer->modelDirectory().'; search stays lexical. Run php artisan vellum:model.');
                    $warned = true;
                }

                continue;
            }

            $started = microtime(true);
            $result = $indexer->build($index);
            $guest = strlen((string) gzencode($result['set']->forGroups(['guest']), 9));

            ($this->line)(sprintf(
                '  semantic%s: %d sections (%d encoded), %d tokens, %d KB gzipped for guests, %d ms',
                $suffix,
                $result['sections'],
                $result['encoded'],
                count($result['set']->tokens),
                (int) round($guest / 1024),
                (int) round((microtime(true) - $started) * 1000),
            ));
        }

        if ($llm !== null && $prune) {
            $llm->prune();
        }
    }
}
