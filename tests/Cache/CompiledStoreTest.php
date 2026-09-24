<?php

declare(strict_types=1);

use Vellum\Cache\CompiledStore;
use Vellum\Content\Document;

it('writes and reads compiled php documents', function (): void {
    $store = new CompiledStore($this->cachePath());

    $document = new Document(
        slug: 'guides/auth',
        title: 'Auth',
        html: '<p>Hi</p>',
        headings: [],
        frontmatter: ['title' => 'Auth'],
        path: '/tmp/auth.md',
        mtime: 123,
        description: 'Desc',
    );

    $store->put($document);

    expect($store->exists('guides/auth'))->toBeTrue();

    $loaded = $store->get('guides/auth');

    expect($loaded)->not->toBeNull()
        ->and($loaded->title)->toBe('Auth')
        ->and($loaded->mtime)->toBe(123)
        ->and($loaded->islands)->toBe([]);
});

it('clears the compiled directory', function (): void {
    $store = new CompiledStore($this->cachePath());
    $store->put(new Document(
        slug: 'a',
        title: 'A',
        html: '',
        headings: [],
        frontmatter: [],
        path: '/tmp/a.md',
        mtime: 1,
    ));

    $store->clear();

    expect($store->exists('a'))->toBeFalse();
});

it('replaces a compiled file whole, leaving nothing half-written behind', function (): void {
    $store = new CompiledStore($this->cachePath());
    $page = static fn (string $title): Document => new Document(
        slug: 'a', title: $title, html: str_repeat('<p>x</p>', 5000), headings: [], frontmatter: [], path: '/tmp/a.md', mtime: 1,
    );

    $store->put($page('First'));
    $store->put($page('Second'));

    expect($store->get('a')?->title)->toBe('Second')
        ->and(glob($this->cachePath().'/*.tmp'))->toBe([]);
});
