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
        ->and($loaded->mtime)->toBe(123);
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
