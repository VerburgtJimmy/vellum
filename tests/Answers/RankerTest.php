<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    $this->rankerFor = function (array $pages): Ranker {
        foreach ($pages as $path => $markdown) {
            $this->writeDoc($path, $markdown);
        }

        $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));

        return new Ranker(AnswerIndex::build($repository->buildAll(), $repository));
    };
});

it('finds the section whose words match, and reports what scored it', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Gating\n\nHide pages from guests.\n",
        'theme.md' => "---\ntitle: Theming\n---\nPresets and colours.\n",
    ]);

    $results = $ranker->search('gating');

    expect($results[0]['record']['id'])->toBe('#gating')
        ->and($results[0]['exact'])->toBeTrue()
        ->and($results[0]['lexical'])->toBe(1.0)
        ->and($results[0]['score'])->toBe(1.0 + Ranker::EXACT_BOOST);
});

it('returns nothing for an empty query, and nothing it cannot match', function (): void {
    $ranker = ($this->rankerFor)(['index.md' => "---\ntitle: Home\n---\nWelcome.\n"]);

    expect($ranker->search(''))->toBe([])
        ->and($ranker->search('   '))->toBe([])
        ->and($ranker->search('kubernetes helm chart'))->toBe([]);
});

it('matches a word the docs never use, through the built-in synonyms', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Dark mode\n\nThe theme follows the system.\n",
        'other.md' => "---\ntitle: Other\n---\nSomething else entirely.\n",
    ]);

    expect($ranker->search('night mode')[0]['record']['id'])->toBe('#dark-mode');
});

it('finds a page by an alias its author gave it', function (): void {
    $ranker = ($this->rankerFor)([
        'billing.md' => "---\ntitle: Billing\naliases:\n  - Invoices\n---\nHow charges work.\n",
        'other.md' => "---\ntitle: Other\n---\nSomething else.\n",
    ]);

    expect($ranker->search('invoices')[0]['record']['page'])->toBe('billing');
});

it('ranks a section the query names above one that only mentions it', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nWelcome.\n\n## Versions\n\nKeep old docs online.\n\n## Search\n\nSearch covers versions, versions and versions.\n",
    ]);

    expect($ranker->search('versions')[0]['record']['id'])->toBe('#versions');
});

it('carries the passage and url with each result', function (): void {
    $ranker = ($this->rankerFor)(['index.md' => "---\ntitle: Deploy\n---\nRun this every deploy:\n\n```bash\nphp artisan vellum:build\n```\n"]);
    $top = $ranker->search('deploy')[0];

    expect($top['record']['passage'])->toBe('Run this every deploy:')
        ->and($top['record']['url'])->toBe('/docs')
        ->and($top['record'])->not->toHaveKey('answer');
});

it('shows at most two sections of one page before any other page', function (): void {
    $ranker = ($this->rankerFor)([
        'index.md' => "---\ntitle: Home\n---\nDeploy.\n\n## Deploy one\n\nDeploy deploy.\n\n## Deploy two\n\nDeploy deploy.\n\n## Deploy three\n\nDeploy deploy.\n",
        'other.md' => "---\ntitle: Other\n---\nOne mention of deploy.\n",
    ]);

    $pages = array_map(static fn (array $hit): string => $hit['record']['page'], $ranker->search('deploy', 4));

    expect(array_slice($pages, 0, Ranker::PER_PAGE + 1))->toContain('other');
});
