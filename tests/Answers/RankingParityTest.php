<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

/*
 * The fixture the browser's ranker is held to: an index and the ranking PHP
 * gives each query over it. VELLUM_FIXTURES=1 rewrites it; every other run
 * checks PHP still agrees with what is committed, and tests/js/ranking.test.js
 * checks the browser does, so the two cannot drift apart quietly.
 */

const PARITY_QUERIES = [
    'how do i install the package',
    'night mode',
    'gating',
    'gat',
    'invoices',
    'supercalifragilisticexpialidocious',
    '',
    'CafÉ RÉSUMÉ naïve',
    'composer',
    'deploy',
    'hide a page',
];

it('agrees with the committed ranking fixture', function (): void {
    $fixture = dirname(__DIR__).'/fixtures/ranking-parity.json';

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nInstall the package with composer.\n\n## Dark mode\n\nThe theme follows the system.\n\n## Deploy\n\nRun the build on every deploy.\n\n## Deploy again\n\nDeploy, deploy, deploy.\n\n## Deploy once more\n\nStill about deploy.\n");
    $this->writeDoc('gating.md', "---\ntitle: Gating\n---\nHide pages from guests.\n");
    $this->writeDoc('billing.md', "---\ntitle: Billing\naliases:\n  - Invoices\n---\nHow charges work. A café résumé, naïvely.\n\n## Deploy notes\n\nBilling changes ship with each deploy.\n");

    $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));
    $index = AnswerIndex::build($repository->buildAll(), $repository);
    $ranker = new Ranker($index);
    $rankings = [];

    foreach (PARITY_QUERIES as $text) {
        $rankings[$text] = array_map(static fn (array $hit): array => [
            'id' => $hit['record']['id'],
            'score' => round($hit['score'], 6),
        ], $ranker->search($text));
    }

    if (getenv('VELLUM_FIXTURES') === '1') {
        file_put_contents($fixture, json_encode([
            'note' => 'Written by tests/Answers/RankingParityTest.php with VELLUM_FIXTURES=1. The browser ranks the index below and must get these rankings and scores.',
            'rankings' => $rankings,
            'index' => [
                'sections' => $index->sections,
                'synonyms' => $index->synonymGroups(),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    $committed = json_decode((string) file_get_contents($fixture), true);

    // JSON brings 1.0 back as an int; compare like for like.
    foreach ($committed['rankings'] as $text => $hits) {
        foreach ($hits as $i => $hit) {
            $committed['rankings'][$text][$i]['score'] = (float) $hit['score'];
        }
    }

    expect($rankings)->toBe($committed['rankings'])
        ->and($rankings['night mode'][0]['id'])->toBe('#dark-mode')
        ->and($rankings['invoices'][0]['id'])->toBe('billing#')
        ->and($rankings['gat'])->not->toBe([]);
});
