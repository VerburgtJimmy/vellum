<?php

declare(strict_types=1);

use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Content\NavigationBuilder;

it('orders pages from meta.json and appends unlisted alphabetically', function (): void {
    $this->writeDoc('meta.json', json_encode([
        'title' => 'Docs',
        'pages' => ['zebra', 'alpha'],
    ], JSON_THROW_ON_ERROR));
    $this->writeDoc('alpha.md', "---\ntitle: Alpha\n---\nA");
    $this->writeDoc('bravo.md', "---\ntitle: Bravo\n---\nB");
    $this->writeDoc('zebra.md', "---\ntitle: Zebra\n---\nZ");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $documents = $repository->buildAll();
    $tree = (new NavigationBuilder($this->docsPath()))->build($documents);

    $slugs = array_map(
        static fn (array $node): string => $node['type'] === 'page' ? $node['slug'] : $node['title'],
        $tree,
    );

    expect($slugs)->toBe(['zebra', 'alpha', 'bravo']);
});

it('supports separators and rest entries in meta.json', function (): void {
    $this->writeDoc('meta.json', json_encode([
        'pages' => ['first', '---Advanced---', '...', 'last'],
    ], JSON_THROW_ON_ERROR));
    $this->writeDoc('first.md', "---\ntitle: First\n---\n1");
    $this->writeDoc('middle.md', "---\ntitle: Middle\n---\nM");
    $this->writeDoc('other.md', "---\ntitle: Other\n---\nO");
    $this->writeDoc('last.md', "---\ntitle: Last\n---\nL");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $tree = (new NavigationBuilder($this->docsPath()))->build($repository->buildAll());

    expect($tree[0]['type'])->toBe('page')->and($tree[0]['slug'])->toBe('first')
        ->and($tree[1])->toMatchArray(['type' => 'separator', 'title' => 'Advanced'])
        ->and($tree[2]['slug'])->toBe('middle')
        ->and($tree[3]['slug'])->toBe('other')
        ->and($tree[4]['slug'])->toBe('last');
});

it('falls back to title case folders and order frontmatter without meta.json', function (): void {
    $this->writeDoc('guides/beta.md', "---\ntitle: Beta\norder: 2\n---\nB");
    $this->writeDoc('guides/alpha.md', "---\ntitle: Alpha\norder: 1\n---\nA");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $tree = (new NavigationBuilder($this->docsPath()))->build($repository->buildAll());

    expect($tree[0]['type'])->toBe('folder')
        ->and($tree[0]['title'])->toBe('Guides')
        ->and($tree[0]['children'][0]['slug'])->toBe('guides/alpha')
        ->and($tree[0]['children'][1]['slug'])->toBe('guides/beta');
});

it('builds nested folders from stub-like meta files', function (): void {
    $this->writeDoc('meta.json', json_encode([
        'pages' => ['index', 'getting-started'],
    ], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Introduction\n---\nHome");
    $this->writeDoc('getting-started/meta.json', json_encode([
        'title' => 'Getting Started',
        'icon' => 'rocket',
        'defaultOpen' => true,
        'pages' => ['installation'],
    ], JSON_THROW_ON_ERROR));
    $this->writeDoc('getting-started/installation.md', "---\ntitle: Installation\n---\nInstall");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $repository->buildAll();

    $nav = $repository->store()->getNav();

    expect($nav)->not->toBeNull()
        ->and($nav[0]['type'])->toBe('page')
        ->and($nav[0]['slug'])->toBe('')
        ->and($nav[1]['type'])->toBe('folder')
        ->and($nav[1]['title'])->toBe('Getting Started')
        ->and($nav[1]['icon'])->toBe('rocket')
        ->and($nav[1]['defaultOpen'])->toBeTrue()
        ->and($nav[1]['children'][0]['slug'])->toBe('getting-started/installation');
});

it('resolves previous and next pages and breadcrumbs', function (): void {
    $this->writeDoc('meta.json', json_encode([
        'pages' => ['index', 'one', 'two'],
    ], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nH");
    $this->writeDoc('one.md', "---\ntitle: One\n---\n1");
    $this->writeDoc('two.md', "---\ntitle: Two\n---\n2");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $repository->buildAll();

    $adjacent = $repository->adjacent('one');

    expect($adjacent['previous']['slug'])->toBe('')
        ->and($adjacent['next']['slug'])->toBe('two');

    $document = $repository->find('one');
    $crumbs = $repository->breadcrumbs($document);

    expect($crumbs[0]['title'])->toBe('Docs')
        ->and($crumbs[array_key_last($crumbs)]['title'])->toBe('One');
});
