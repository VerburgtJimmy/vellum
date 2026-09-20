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

/*
 * Picks answers.card_threshold: the confidence at which a card is worth
 * showing. Tuned on the golden and held-out sets only; the third set decided
 * the ranking and is not touched here.
 */
it('reports the card threshold tradeoff', function (): void {
    $docs = (string) realpath(__DIR__.'/../../docs');
    $repository = new ContentRepository(contentPath: $docs, store: new CompiledStore($this->cachePath()));
    $documents = $repository->buildAll();
    $index = AnswerIndex::build($documents, $repository);
    $indexer = new SemanticIndexer(rtrim((string) getenv('VELLUM_EVAL_MODELS'), '/').'/potion-base-8M', $this->cachePath().'/semantic');
    $set = $indexer->build($index)['set'];
    $ranker = new Ranker($index, new SemanticQuery($set->forGroups(['guest'])));

    $sections = array_map(static fn (array $record): array => [
        'page' => $record['page'],
        'anchor' => $record['anchor'],
        'parent' => $record['parent'],
    ], $index->sections);

    $questions = [
        ...Yaml::parseFile($docs.'/questions.yml'),
        ...Yaml::parseFile(__DIR__.'/heldout.yml'),
    ];

    $rows = [];

    foreach ($questions as $entry) {
        $result = $ranker->search($entry['q']);
        $top = $result['results'][0] ?? null;
        $rows[] = [
            'confidence' => $result['confidence'],
            'right' => $top !== null && Metrics::hits($entry, $sections[array_search($top['record'], $index->sections, true)]),
            'kind' => $entry['kind'],
        ];
    }

    $report = "# Card threshold\n\n".count($rows)." questions from the golden and held-out sets.\n\n";
    $report .= "| Threshold | Cards shown | Right | Wrong | Cards that are right | Right answers held back |\n| --- | --- | --- | --- | --- | --- |\n";
    $rightOverall = count(array_filter($rows, static fn (array $r): bool => $r['right']));

    foreach ([0.0, 0.3, 0.4, 0.45, 0.5, 0.55, 0.6, 0.65, 0.7, 0.8] as $threshold) {
        $shown = array_filter($rows, static fn (array $r): bool => $r['confidence'] >= $threshold);
        $right = count(array_filter($shown, static fn (array $r): bool => $r['right']));
        $wrong = count($shown) - $right;
        $report .= sprintf(
            "| %.2f | %d | %d | %d | %s | %d |\n",
            $threshold, count($shown), $right, $wrong,
            count($shown) === 0 ? 'n/a' : sprintf('%.0f%%', $right / count($shown) * 100),
            $rightOverall - $right,
        );
    }

    file_put_contents(__DIR__.'/card-threshold.md', $report);
    fwrite(STDERR, $report);

    expect($rows)->not->toBeEmpty();
})->skip(fn (): bool => getenv('VELLUM_EVAL') !== '1', 'Set VELLUM_EVAL=1 and VELLUM_EVAL_MODELS to tune the card threshold.');
