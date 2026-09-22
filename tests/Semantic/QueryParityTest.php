<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Answers\SemanticIndexer;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Semantic\SemanticQuery;
use Vellum\Tests\Semantic\FakeModel;

/*
 * The fixture the browser is held to: a real semantic file and the cosines PHP
 * gets from it. VELLUM_FIXTURES=1 rewrites it; every other run checks PHP still
 * agrees with what is committed, so the two sides cannot drift apart quietly.
 */

const PARITY_QUERIES = [
    'how do i install the package',
    'night mode',
    'gating',
    'supercalifragilisticexpialidocious',
    '',
    'CafÉ RÉSUMÉ naïve',
    'composer',
    'theme',
    'hide a page',
];

it('agrees with the committed query fixture', function (): void {
    $directory = dirname(__DIR__).'/fixtures/semantic';
    $modelRoot = sys_get_temp_dir().'/vellum-parity-'.$this->fixtureId();
    FakeModel::write($modelRoot.'/fake-model');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nInstall the package with composer.\n\n## Dark mode\n\nThe theme follows the system.\n");
    $this->writeDoc('gating.md', "---\ntitle: Gating\n---\nHide pages from guests.\n");

    $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));
    $index = AnswerIndex::build($repository->buildAll(), $repository);
    $set = (new SemanticIndexer($modelRoot.'/fake-model', $this->cachePath().'/semantic'))->build($index)['set'];
    $bytes = $set->forGroups(['guest']);
    $query = new SemanticQuery($bytes);

    $scores = [];
    $rankings = [];
    $ranker = new Ranker($index, $query);

    foreach (PARITY_QUERIES as $text) {
        $scores[$text] = array_map(static fn (float $score): float => round($score, 6), $query->scores($text));
        $result = $ranker->search($text);
        $rankings[$text] = [
            'ids' => array_map(static fn (array $hit): string => $hit['record']['id'], $result['results']),
            'confidence' => round($result['confidence'], 6),
            'card' => $result['card']['record']['id'] ?? null,
        ];
    }

    $this->deleteDirectory($modelRoot);

    if (getenv('VELLUM_FIXTURES') === '1') {
        file_put_contents($directory.'/query-parity.bin', $bytes);
        file_put_contents($directory.'/query-parity.json', json_encode([
            'note' => 'Written by tests/Semantic/QueryParityTest.php with VELLUM_FIXTURES=1. The browser reads the .bin and the index below, and must get these cosines, rankings and confidences.',
            'queries' => $scores,
            'rankings' => $rankings,
            'index' => [
                'sections' => $index->sections,
                'synonyms' => $index->synonymGroups(),
                'threshold' => (float) config('vellum.answers.card_threshold', 0.75),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    $committed = json_decode((string) file_get_contents($directory.'/query-parity.json'), true);

    // JSON brings 0.0 back as an int; compare like for like.
    foreach ($committed['rankings'] as $text => $ranking) {
        $committed['rankings'][$text]['confidence'] = (float) $ranking['confidence'];
    }

    foreach ($committed['queries'] as $text => $byId) {
        $committed['queries'][$text] = array_map('floatval', $byId);
    }

    expect(file_get_contents($directory.'/query-parity.bin'))->toBe($bytes)
        ->and($scores)->toBe($committed['queries'])
        ->and($rankings)->toBe($committed['rankings'])
        ->and($index->sections)->toBe($committed['index']['sections']);
});
