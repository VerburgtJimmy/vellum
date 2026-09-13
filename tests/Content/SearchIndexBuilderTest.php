<?php

declare(strict_types=1);

use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Content\SearchIndexBuilder;

it('builds a minisearch document list with stable hash', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: Welcome\n---\nHello **world**\n\n## Section\n");
    $this->writeDoc('guide.md', "---\ntitle: Guide\n---\nGuide body");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $documents = $repository->buildAll();

    $built = (new SearchIndexBuilder)->build($documents);

    expect($built['documents'])->toHaveCount(2)
        ->and($built['hash'])->toBeString()->not->toBeEmpty();

    $home = collect($built['documents'])->firstWhere('id', 'index');

    expect($home)->not->toBeNull()
        ->and($home['title'])->toBe('Home')
        ->and($home['description'])->toBe('Welcome')
        ->and($home['content'])->toContain('Hello world')
        ->and($home['url'])->toBe('/docs')
        ->and($home['headings'])->toContain('Section')
        ->and($home['access'])->toBe('guest');

    $manifest = $repository->store()->getManifest();

    expect($manifest)->not->toBeNull()
        ->and($manifest['search_hash'])->toBe($built['hash'])
        ->and(is_file($this->cachePath().'/search-index.json'))->toBeTrue()
        ->and(is_file($this->cachePath().'/search-index-'.$built['hash'].'.json'))->toBeTrue();
});

it('records frontmatter access on search documents', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nNope");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $built = (new SearchIndexBuilder)->build($repository->buildAll());

    $secret = collect($built['documents'])->firstWhere('id', 'secret');

    expect($secret)->not->toBeNull()
        ->and($secret['access'])->toBe('auth');
});
