<?php

declare(strict_types=1);

use Vellum\Cache\FragmentCache;
use Vellum\Content\Document;
use Vellum\Markdown\Islands\Island;

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

it('caches vellum components but renders a host component every time', function (): void {
    $island = static fn (string $name, array $children = []): Island => new Island(
        id: $name, name: $name, attributes: [], slotHtml: '', children: $children, selfClosing: true,
    );
    $page = static fn (array $islands): Document => new Document(
        slug: 'index', title: 'Home', html: '<p>Hi</p>', headings: [], frontmatter: [],
        path: '/tmp/index.md', mtime: 1, islands: $islands,
    );

    $cache = new FragmentCache;
    $hits = 0;
    $resolve = function () use (&$hits): string {
        return 'rendered-'.++$hits;
    };

    $builtIn = $page([$island('vellum::callout', [$island('vellum::env')])]);
    $cache->remember($builtIn, $resolve);
    expect($cache->remember($builtIn, $resolve))->toBe('rendered-1');

    $nested = $page([$island('vellum::callout', [$island('whoami')])]);
    expect($cache->remember($nested, $resolve))->toBe('rendered-2')
        ->and($cache->remember($nested, $resolve))->toBe('rendered-3');
});
