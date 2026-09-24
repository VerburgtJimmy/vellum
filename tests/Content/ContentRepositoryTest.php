<?php

declare(strict_types=1);

use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;

it('discovers nested documents and resolves slugs', function (): void {
    $this->writeDoc('index.md', "# Home\n");
    $this->writeDoc('guides/authentication.md', <<<'MD'
---
title: Authentication
---
# Authentication
MD);

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );

    $files = $repository->discoverSourceFiles();
    $slugs = array_column($files, 'slug');

    expect($slugs)->toContain('')
        ->and($slugs)->toContain('guides/authentication');
});

it('honours frontmatter slug overrides', function (): void {
    $this->writeDoc('old-name.md', <<<'MD'
---
title: Renamed
slug: brand-new-slug
---
Body
MD);

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );

    $files = $repository->discoverSourceFiles();

    expect($files[0]['slug'])->toBe('brand-new-slug');
});

it('falls back to the first heading then title case filename', function (): void {
    $this->writeDoc('plain-page.md', "# From Heading\n\nHi");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );

    $document = $repository->find('plain-page');

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('From Heading');
});

it('compiles documents into php cache files', function (): void {
    $this->writeDoc('index.md', <<<'MD'
---
title: Home
description: The home page
---
Hello **world**
MD);

    $store = new CompiledStore($this->cachePath());
    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: $store,
    );

    $documents = $repository->buildAll();

    expect($documents)->toHaveCount(1)
        ->and($store->exists(''))->toBeTrue();

    $loaded = $store->get('');

    expect($loaded)->toBeInstanceOf(Document::class)
        ->and($loaded->title)->toBe('Home')
        ->and($loaded->html)->toContain('<strong>world</strong>')
        ->and($loaded->description)->toBe('The home page');
});

it('serves index.md at the folder path', function (): void {
    $this->writeDoc('guides/index.md', <<<'MD'
---
title: Guides
---
Guides home
MD);

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );

    $document = $repository->find('guides');

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('Guides');
});

it('does not re-read sources in production when compiled', function (): void {
    $path = $this->writeDoc('cached.md', "---\ntitle: Original\n---\nOne");

    $store = new CompiledStore($this->cachePath());
    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: $store,
        isLocal: false,
    );

    $first = $repository->find('cached');
    expect($first?->title)->toBe('Original');

    file_put_contents($path, "---\ntitle: Changed\n---\nTwo");
    touch($path, time() + 10);

    $second = $repository->find('cached');

    expect($second?->title)->toBe('Original')
        ->and($second?->html)->toContain('One');
});

it('recompiles a document in local when mtime changes', function (): void {
    $path = $this->writeDoc('local.md', "---\ntitle: Original\n---\nOne");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
        isLocal: true,
    );

    expect($repository->find('local')?->title)->toBe('Original');

    file_put_contents($path, "---\ntitle: Updated\n---\nTwo");
    touch($path, time() + 10);

    expect($repository->find('local')?->title)->toBe('Updated');
});

it('resolves versioned documents and omits the latest version from URLs', function (): void {
    $this->writeDoc('v2/guides/auth.md', "---\ntitle: V2 Auth\n---\nV2");
    $this->writeDoc('v1/guides/auth.md', "---\ntitle: V1 Auth\n---\nV1");

    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
        versionsEnabled: true,
        latestVersion: 'v2',
        versions: ['v2', 'v1'],
    );

    expect($repository->shouldRedirectToUnprefixed('guides/auth'))->toBeFalse()
        ->and($repository->shouldRedirectToUnprefixed('v2/guides/auth'))->toBeTrue()
        ->and($repository->unprefixedPath('v2/guides/auth'))->toBe('guides/auth')
        ->and($repository->hrefFor('guides/auth', 'v2'))->toBe('/docs/guides/auth')
        ->and($repository->hrefFor('guides/auth', 'v1'))->toBe('/docs/v1/guides/auth')
        ->and($repository->parseRequestSlug('guides/auth'))->toBe(['version' => 'v2', 'slug' => 'guides/auth'])
        ->and($repository->parseRequestSlug('v1/guides/auth'))->toBe(['version' => 'v1', 'slug' => 'guides/auth']);

    $document = $repository->find('guides/auth', 'v2');

    expect($document?->title)->toBe('V2 Auth')
        ->and($document?->version)->toBe('v2');
});

it('rebuilds the local sidebar when an edit keeps the file the same size', function (): void {
    $this->writeDoc('alpha.md', "---\ntitle: Alpha\n---\nA");
    $this->writeDoc('beta.md', "---\ntitle: Beta\n---\nB");
    $meta = $this->writeDoc('meta.json', '{"pages": ["alpha", "beta"]}');

    $repository = fn (): ContentRepository => new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
        isLocal: true,
    );

    expect(array_column($repository()->navigation(), 'title'))->toBe(['Alpha', 'Beta']);

    file_put_contents($meta, '{"pages": ["beta", "alpha"]}');
    touch($meta, time() + 10);

    expect(array_column($repository()->navigation(), 'title'))->toBe(['Beta', 'Alpha']);
});
