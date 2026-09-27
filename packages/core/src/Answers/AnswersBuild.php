<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Closure;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;

/**
 * Builds the search index for each docs version, then asks the version's
 * questions file against it. Shared by vellum:build and vellum:index, which
 * only differ in what else they do.
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
     * @return int how many versions failed their questions file: fewer
     *             answers than checks.search_min asks for, or, under strict,
     *             a question whose target is gone
     */
    public function run(ContentRepository $repository, array $documents): int
    {
        $byVersion = [];

        foreach ($documents as $document) {
            $byVersion[$document->version ?? ''][] = $document;
        }

        $short = 0;

        foreach ($byVersion as $version => $group) {
            $root = $repository->store()->versionPath($version === '' ? null : $version);
            $index = AnswerIndex::build($group, $repository);
            $suffix = $version === '' ? '' : " {$version}";

            $index->save($root.'/answers');

            ($this->line)(sprintf(
                '  search index%s: %d sections, %d questions',
                $suffix,
                count($index->sections),
                array_sum(array_map(static fn (array $record): int => count($record['questions']), $index->sections)),
            ));

            $short += $this->check($repository, $index, $version, $suffix);
        }

        return $short;
    }

    /**
     * Runs this version's questions file, if it has one and the check is on.
     */
    private function check(ContentRepository $repository, AnswerIndex $index, string $version, string $suffix): int
    {
        if (! $this->forceCheck && ! (bool) config('vellum.checks.search', true)) {
            return 0;
        }

        $questions = SearchCheck::read($repository->contentPath($version === '' ? null : $version).'/'.SearchCheck::FILE);

        if ($questions === null) {
            return 0;
        }

        // The check reads what a guest would get, since that is the copy most
        // readers search and the one a questions file is written for.
        $result = $questions->run($index->forAccess(['guest']));
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
