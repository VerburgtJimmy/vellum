<?php

declare(strict_types=1);

use Vellum\Cache\FragmentCache;
use Vellum\Content\Document;

it('renders an island fragment once until the cache is cleared', function (): void {
    $document = new Document(
        slug: 'index',
        title: 'Home',
        html: '<p>Hi</p>',
        headings: [],
        frontmatter: [],
        path: '/tmp/index.md',
        mtime: 1,
    );

    $cache = new FragmentCache;
    $hits = 0;
    $resolve = function () use (&$hits): string {
        $hits++;

        return 'rendered-'.$hits;
    };

    expect($cache->remember($document, $resolve))->toBe('rendered-1')
        ->and($cache->remember($document, $resolve))->toBe('rendered-1')
        ->and($hits)->toBe(1);

    $cache->clear();

    expect($cache->remember($document, $resolve))->toBe('rendered-2')
        ->and($hits)->toBe(2);
});

it('busts the fragment when component config changes', function (): void {
    $document = new Document(
        slug: 'index',
        title: 'Home',
        html: '<p>Hi</p>',
        headings: [],
        frontmatter: [],
        path: '/tmp/index.md',
        mtime: 1,
    );

    $cache = new FragmentCache;
    $cache->remember($document, static fn (): string => 'first');

    config()->set('vellum.components.namespaces', ['vellum', '']);

    expect($cache->remember($document, static fn (): string => 'second'))->toBe('second');
});
