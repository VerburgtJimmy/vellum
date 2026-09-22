<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Closure;
use Vellum\Answers\Llm\LlmQuestions;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Semantic\SemanticQuery;

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
        private readonly bool $forceCheck = false,
        private readonly bool $strict = false,
    ) {}

    /**
     * @param  list<Document>  $documents
     * @param  bool  $prune  drop cached questions for sections that are gone,
     *                       which is only safe when every version was built
     * @return int how many versions failed their questions file: fewer
     *             answers than checks.search_min asks for, or, under strict,
     *             a question whose target is gone
     */
    public function run(ContentRepository $repository, array $documents, bool $prune = true): int
    {
        if (! (bool) config('vellum.answers.enabled', true)) {
            return 0;
        }

        $byVersion = [];

        foreach ($documents as $document) {
            $byVersion[$document->version ?? ''][] = $document;
        }

        $semantic = (bool) config('vellum.answers.semantic', true);
        $llm = LlmQuestions::fromConfig();
        $warned = false;
        $short = 0;

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
                $short += $this->check($repository, $index, null, $version, $suffix);

                continue;
            }

            $indexer = SemanticIndexer::fromConfig($root.'/semantic');

            if (! $indexer->hasModel()) {
                if (! $warned) {
                    ($this->warn)('No embedding model in '.$indexer->modelDirectory().'; search stays lexical. Run php artisan vellum:model.');
                    $warned = true;
                }

                $short += $this->check($repository, $index, null, $version, $suffix);

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

            // The check reads what a guest would get, since that is the copy
            // most readers search and the one a questions file is written for.
            $short += $this->check($repository, $index, new SemanticQuery($result['set']->forGroups(['guest'])), $version, $suffix);
        }

        if ($llm !== null && $prune) {
            $llm->prune();
        }

        return $short;
    }

    /**
     * Runs this version's questions file, if it has one and the check is on.
     */
    private function check(ContentRepository $repository, AnswerIndex $index, ?SemanticQuery $semantic, string $version, string $suffix): int
    {
        if (! $this->forceCheck && ! (bool) config('vellum.checks.search', true)) {
            return 0;
        }

        $questions = SearchCheck::read($repository->contentPath($version === '' ? null : $version).'/'.SearchCheck::FILE);

        if ($questions === null) {
            return 0;
        }

        $result = $questions->run($index->forAccess(['guest']), $semantic);
        $rate = $result['asked'] === 0 ? 0.0 : $result['found'] / $result['asked'];

        ($this->line)(sprintf(
            '  search check%s: %d of %d questions answered in the top %d (%d%%)',
            $suffix,
            $result['found'],
            $result['asked'],
            SearchCheck::DEPTH,
            (int) round($rate * 100),
        ));

        foreach (array_slice($result['missed'], 0, 3) as $missed) {
            ($this->line)('    missed: '.$missed);
        }

        if (count($result['missed']) > 3) {
            ($this->line)(sprintf('    and %d more', count($result['missed']) - 3));
        }

        foreach ($result['broken'] as $broken) {
            ($this->warn)('  broken question: '.$broken);
        }

        // A question nothing can answer is a broken test, not a search
        // failure, so it is a warning unless the build is strict.
        if ($result['broken'] !== [] && $this->strict) {
            return 1;
        }

        $minimum = (float) config('vellum.checks.search_min', 0.0);

        if ($result['asked'] > 0 && $minimum > 0.0 && $rate < $minimum) {
            ($this->warn)(sprintf(
                '  search%s answered %d%% of its questions, below the %d%% checks.search_min asks for.',
                $suffix,
                (int) round($rate * 100),
                (int) round($minimum * 100),
            ));

            return 1;
        }

        return 0;
    }
}
