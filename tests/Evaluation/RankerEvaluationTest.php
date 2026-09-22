<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Answers\SemanticIndexer;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Semantic\SemanticQuery;
use Vellum\Tests\Evaluation\Metrics;
use Vellum\Tests\Evaluation\WrittenQuestions;

/*
 * What the shipped ranker scores on every question set, as it runs in
 * production: the answer index, the semantic file, synonym expansion and the
 * per-page cap, including the questions a model wrote into the cache. Run
 * with VELLUM_EVAL=1 and VELLUM_EVAL_MODELS set.
 */
it('reports what the shipped ranker finds', function (): void {
    $docs = (string) realpath(__DIR__.'/../../docs');
    $repository = new ContentRepository(contentPath: $docs, store: new CompiledStore($this->cachePath()));
    $index = AnswerIndex::build($repository->buildAll(), $repository, WrittenQuestions::all());
    $set = (new SemanticIndexer(rtrim((string) getenv('VELLUM_EVAL_MODELS'), '/').'/potion-base-8M', $this->cachePath().'/semantic'))->build($index)['set'];
    $ranker = new Ranker($index, new SemanticQuery($set->forGroups(['guest'])));
    $sections = array_map(static fn (array $record): array => [
        'page' => $record['page'],
        'anchor' => $record['anchor'],
        'parent' => $record['parent'],
    ], $index->sections);
    $byId = array_flip(array_column($index->sections, 'id'));

    $sets = [
        'golden' => Yaml::parseFile($docs.'/questions.yml'),
        'held-out' => Yaml::parseFile(__DIR__.'/heldout.yml'),
        'third' => Yaml::parseFile(__DIR__.'/third.yml'),
    ];

    $report = "# The shipped ranker\n\n| Set | R@1 | R@5 | Page R@5 | Literal R@5 | Paraphrase R@5 | Cards | Cards right |\n| --- | --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($sets as $name => $questions) {
        $ranking = [];
        $cards = 0;
        $right = 0;
        $onPage = 0;

        foreach ($questions as $i => $entry) {
            $result = $ranker->search($entry['q']);
            $ranking[$i] = array_map(static fn (array $hit): int => $byId[$hit['record']['id']], $result['results']);

            // Did any of the top five come from a page that answers it? A
            // reader who lands on the right page has what they asked for.
            $pages = array_map(static fn (array $hit): string => $hit['record']['page'], array_slice($result['results'], 0, 5));
            $wanted = array_map(static fn (array $target): string => $target['page'], [$entry, ...($entry['also'] ?? [])]);
            $onPage += array_intersect($pages, $wanted) !== [] ? 1 : 0;

            if ($result['card'] !== null) {
                $cards++;
                $right += Metrics::hits($entry, $sections[$byId[$result['card']['record']['id']]]) ? 1 : 0;
            }
        }

        $recall = Metrics::recall($ranking, $questions, $sections);
        $pct = static fn (float $v): string => sprintf('%.0f%%', $v * 100);
        $report .= sprintf(
            "| %s (%d) | %s | %s | %s | %s | %s | %d | %s |\n",
            $name, count($questions),
            $pct($recall['all'][1]), $pct($recall['all'][5]), $pct($onPage / max(1, count($questions))),
            $pct($recall['literal'][5] ?? 0.0), $pct($recall['paraphrase'][5] ?? 0.0),
            $cards, $cards === 0 ? 'n/a' : $pct($right / $cards),
        );
    }

    file_put_contents(__DIR__.'/shipped-ranker.md', $report);
    fwrite(STDERR, $report);

    expect($sets)->not->toBeEmpty();
})->skip(fn (): bool => getenv('VELLUM_EVAL') !== '1', 'Set VELLUM_EVAL=1 and VELLUM_EVAL_MODELS to evaluate the shipped ranker.');
