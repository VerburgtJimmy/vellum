<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
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

    foreach (PARITY_QUERIES as $text) {
        $scores[$text] = array_map(static fn (float $score): float => round($score, 6), $query->scores($text));
    }

    $this->deleteDirectory($modelRoot);

    if (getenv('VELLUM_FIXTURES') === '1') {
        file_put_contents($directory.'/query-parity.bin', $bytes);
        file_put_contents($directory.'/query-parity.json', json_encode([
            'note' => 'Written by tests/Semantic/QueryParityTest.php with VELLUM_FIXTURES=1. The browser reads the .bin and must get these cosines.',
            'queries' => $scores,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }

    $committed = json_decode((string) file_get_contents($directory.'/query-parity.json'), true);

    expect(file_get_contents($directory.'/query-parity.bin'))->toBe($bytes)
        ->and($scores)->toBe($committed['queries']);
});
