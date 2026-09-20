<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Answers\SemanticIndexer;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Semantic\SemanticQuery;
use Vellum\Tests\Semantic\FakeModel;

beforeEach(function (): void {
    $this->modelRoot = sys_get_temp_dir().'/vellum-rank-'.$this->fixtureId();
    FakeModel::write($this->modelRoot.'/fake-model');

    $this->rankerFor = function (array $pages, bool $semantic = false): Ranker {
        foreach ($pages as $path => $markdown) {
            $this->writeDoc($path, $markdown);
        }

        $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));
        $index = AnswerIndex::build($repository->buildAll(), $repository);

        if (! $semantic) {
            return new Ranker($index);
        }

        $set = (new SemanticIndexer($this->modelRoot.'/fake-model', $this->cachePath().'/semantic'))->build($index)['set'];

        return new Ranker($index, new SemanticQuery($set->forGroups(['guest'])));
    };
});

afterEach(function (): void {
    $this->deleteDirectory($this->modelRoot);
});

it('finds the section whose words match, and reports what scored it', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Gating\n\nHide pages from guests.\n",
        'theme.md' => "---\ntitle: Theming\n---\nPresets and colours.\n",
    ]);

    $result = $ranker->search('gating');

    expect($result['results'][0]['record']['id'])->toBe('#gating')
        ->and($result['results'][0]['exact'])->toBeTrue()
        ->and($result['results'][0]['lexical'])->toBe(1.0)
        ->and($result['results'][0]['score'])->toBeGreaterThan(0.0);
});

it('answers nothing for an empty query, and nothing it cannot match', function (): void {
    $ranker = ($this->rankerFor)(['index.md' => "---\ntitle: Home\n---\nWelcome.\n"]);

    expect($ranker->search('')['results'])->toBe([])
        ->and($ranker->search('   ')['card'])->toBeNull()
        ->and($ranker->search('kubernetes helm chart')['results'])->toBe([]);
});

it('matches a word the docs never use, through the built-in synonyms', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Dark mode\n\nThe theme follows the system.\n",
        'other.md' => "---\ntitle: Other\n---\nSomething else entirely.\n",
    ]);

    expect($ranker->search('night mode')['results'][0]['record']['id'])->toBe('#dark-mode');
});

it('finds a page by an alias its author gave it', function (): void {
    $ranker = ($this->rankerFor)([
        'billing.md' => "---\ntitle: Billing\naliases:\n  - Invoices\n---\nHow charges work.\n",
        'other.md' => "---\ntitle: Other\n---\nSomething else.\n",
    ]);

    expect($ranker->search('invoices')['results'][0]['record']['page'])->toBe('billing');
});

it('shows a card only when it is sure, and the card is the top result', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Gating\n\nHide pages from guests behind a gate.\n",
        'other.md' => "---\ntitle: Other\n---\nSomething else.\n",
    ]);

    config(['vellum.answers.card_threshold' => 0.0]);
    $sure = $ranker->search('gating');

    config(['vellum.answers.card_threshold' => 1.01]);
    $unsure = $ranker->search('gating');

    expect($sure['card'])->toBe($sure['results'][0])
        ->and($sure['confidence'])->toBeGreaterThan(0.0)
        ->and($unsure['card'])->toBeNull()
        ->and($unsure['results'])->not->toBeEmpty();
});

it('scores a section it is sure of above one it is not', function (): void {
    $clear = Ranker::confidence([
        ['record' => [], 'score' => 0.9, 'cosine' => 0.8, 'lexical' => 1.0, 'exact' => true],
        ['record' => [], 'score' => 0.2, 'cosine' => 0.2, 'lexical' => 0.1, 'exact' => false],
    ]);
    $muddy = Ranker::confidence([
        ['record' => [], 'score' => 0.31, 'cosine' => 0.3, 'lexical' => 0.1, 'exact' => false],
        ['record' => [], 'score' => 0.30, 'cosine' => 0.3, 'lexical' => 0.1, 'exact' => false],
    ]);

    expect($clear)->toBeGreaterThan(0.85)
        ->and($muddy)->toBeLessThan(0.3)
        ->and(Ranker::confidence([]))->toBe(0.0);
});

it('carries the answer and passage with each result', function (): void {
    $ranker = ($this->rankerFor)(['index.md' => "---\ntitle: Deploy\n---\nRun this every deploy:\n\n```bash\nphp artisan vellum:build\n```\n"]);
    $top = $ranker->search('deploy')['results'][0];

    expect($top['record']['answer'])->toBe(['type' => 'command', 'command' => 'php artisan vellum:build'])
        ->and($top['record']['passage'])->toBe('Run this every deploy:')
        ->and($top['record']['url'])->toBe('/docs');
});

it('ranks with the semantic file when there is one', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Gating\n\nHide pages from guests.\n",
    ], semantic: true);

    $result = $ranker->search('gating');

    expect($result['results'][0]['cosine'])->not->toBe(0.0)
        ->and($result['results'])->not->toBeEmpty();
});
