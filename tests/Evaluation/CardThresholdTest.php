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
 * Picks answers.card_threshold: the confidence at which a card is worth
 * showing. A card states an answer, so a wrong one costs more than a missing
 * one; the threshold is chosen for precision, and the report says what recall
 * that costs. Every set is reported, and the worst of the three is what the
 * threshold has to clear.
 */
it('reports the card threshold tradeoff', function (): void {
    $docs = (string) realpath(__DIR__.'/../../docs');
    $repository = new ContentRepository(contentPath: $docs, store: new CompiledStore($this->cachePath()));
    $index = AnswerIndex::build($repository->buildAll(), $repository, WrittenQuestions::all());
    $indexer = new SemanticIndexer(rtrim((string) getenv('VELLUM_EVAL_MODELS'), '/').'/potion-base-8M', $this->cachePath().'/semantic');
    $ranker = new Ranker($index, new SemanticQuery($indexer->build($index)['set']->forGroups(['guest'])));

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

    // One search per question; the sweep then re-reads the same confidences.
    $rows = [];

    foreach ($sets as $name => $questions) {
        foreach ($questions as $entry) {
            $result = $ranker->search($entry['q']);

            // The card is always the top result; only the threshold decides
            // whether it is shown, so the sweep reads the top result directly.
            $top = $result['results'][0] ?? null;

            $wanted = array_map(static fn (array $target): string => $target['page'], [$entry, ...($entry['also'] ?? [])]);

            $rows[$name][] = [
                'confidence' => $result['confidence'],
                'right' => $top !== null && Metrics::hits($entry, $sections[$byId[$top['record']['id']]]),
                'page' => $top !== null && in_array($top['record']['page'], $wanted, true),
                'found' => array_filter(
                    array_slice($result['results'], 0, 5),
                    static fn (array $hit): bool => Metrics::hits($entry, $sections[$byId[$hit['record']['id']]]),
                ) !== [],
            ];
        }
    }

    $thresholds = [0.55, 0.6, 0.65, 0.7, 0.75, 0.8, 0.85];
    $report = "# Card threshold\n\nCards shown at each threshold, and how many of them name a section that\nanswers the question. \"On the page\" counts a card whose section is on a page\nthat answers it, which is the weaker claim a reader still lands on.\n\n";
    $report .= '| Threshold | '.implode(' | ', array_map(static fn (string $n): string => $n.' cards, right', array_keys($sets)))." | All three | On the page |\n";
    $report .= '| --- '.str_repeat('| --- ', count($sets) + 2)."|\n";

    $cell = static fn (int $shown, int $right): string => $shown === 0
        ? '0, n/a'
        : sprintf('%d, %d (%.0f%%)', $shown, $right, $right / $shown * 100);

    foreach ($thresholds as $threshold) {
        $cells = [];
        $total = 0;
        $totalRight = 0;
        $totalPage = 0;

        foreach ($sets as $name => $questions) {
            $shown = array_filter($rows[$name], static fn (array $r): bool => $r['confidence'] >= $threshold);
            $right = count(array_filter($shown, static fn (array $r): bool => $r['right']));
            $cells[] = $cell(count($shown), $right);
            $total += count($shown);
            $totalRight += $right;
            $totalPage += count(array_filter($shown, static fn (array $r): bool => $r['page']));
        }

        $report .= sprintf(
            "| %.2f | %s | %s | %s |\n",
            $threshold, implode(' | ', $cells), $cell($total, $totalRight), $cell($total, $totalPage),
        );
    }

    $report .= "\n## What a threshold costs\n\nQuestions whose answer is in the top five but that get no card, per set.\n\n";
    $report .= '| Threshold | '.implode(' | ', array_keys($sets))." |\n| --- ".str_repeat('| --- ', count($sets))."|\n";

    foreach ($thresholds as $threshold) {
        $cells = [];

        foreach ($sets as $name => $questions) {
            $missed = array_filter($rows[$name], static fn (array $r): bool => $r['found'] && ! ($r['right'] && $r['confidence'] >= $threshold));
            $cells[] = sprintf('%d of %d', count($missed), count(array_filter($rows[$name], static fn (array $r): bool => $r['found'])));
        }

        $report .= sprintf("| %.2f | %s |\n", $threshold, implode(' | ', $cells));
    }

    file_put_contents(__DIR__.'/card-threshold.md', $report);
    fwrite(STDERR, $report);

    expect($rows)->not->toBeEmpty();
})->skip(fn (): bool => getenv('VELLUM_EVAL') !== '1', 'Set VELLUM_EVAL=1 and VELLUM_EVAL_MODELS to tune the card threshold.');
