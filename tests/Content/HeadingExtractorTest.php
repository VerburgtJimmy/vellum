<?php

declare(strict_types=1);

use Vellum\Content\HeadingExtractor;
use Vellum\Markdown\MarkdownRenderer;

it('applies heading ids in the commonmark pipeline for h2-h4 only', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
# Page Title

## Alpha

### Beta

#### Gamma

##### Too Deep

###### Also Deep
MD);

    expect($html)
        ->toMatch('/<h2[^>]*id="alpha"/')
        ->toMatch('/<h3[^>]*id="beta"/')
        ->toMatch('/<h4[^>]*id="gamma"/')
        ->not->toMatch('/<h5[^>]*id=/')
        ->not->toMatch('/<h6[^>]*id=/')
        ->not->toMatch('/<h1[^>]*id=/');
});

it('suffixes duplicate heading ids with -1 and -2', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
## Hello

## Hello

## Hello
MD);

    expect($html)
        ->toMatch('/<h2[^>]*id="hello"/')
        ->toMatch('/<h2[^>]*id="hello-1"/')
        ->toMatch('/<h2[^>]*id="hello-2"/');
});

it('reads toc entries from the ast for h2-h4 including duplicate suffixes', function (): void {
    $converted = (new MarkdownRenderer)->convert(<<<'MD'
## Hello

### Nested

## Hello

#### Deep

##### Ignored
MD);

    $headings = $converted['headings'];

    expect($headings)->toHaveCount(4)
        ->and($headings[0])->toMatchArray(['id' => 'hello', 'text' => 'Hello', 'level' => 2])
        ->and($headings[1])->toMatchArray(['id' => 'nested', 'text' => 'Nested', 'level' => 3])
        ->and($headings[2])->toMatchArray(['id' => 'hello-1', 'text' => 'Hello', 'level' => 2])
        ->and($headings[3])->toMatchArray(['id' => 'deep', 'text' => 'Deep', 'level' => 4]);
});

it('nests headings into a toc tree', function (): void {
    $tree = (new HeadingExtractor)->nest([
        ['id' => 'a', 'text' => 'A', 'level' => 2],
        ['id' => 'b', 'text' => 'B', 'level' => 3],
        ['id' => 'c', 'text' => 'C', 'level' => 3],
        ['id' => 'd', 'text' => 'D', 'level' => 2],
    ]);

    expect($tree)->toHaveCount(2)
        ->and($tree[0]['id'])->toBe('a')
        ->and($tree[0]['children'])->toHaveCount(2)
        ->and($tree[0]['children'][0]['id'])->toBe('b')
        ->and($tree[1]['id'])->toBe('d')
        ->and($tree[1]['children'])->toBe([]);
});
