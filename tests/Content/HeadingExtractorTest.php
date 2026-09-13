<?php

declare(strict_types=1);

use Vellum\Content\HeadingExtractor;
use Vellum\Markdown\MarkdownRenderer;

it('applies heading ids in the commonmark pipeline for h2-h6', function (): void {
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
        ->toMatch('/<h5[^>]*id="too-deep"/')
        ->toMatch('/<h6[^>]*id="also-deep"/')
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

it('reads toc entries from the ast for h2-h6 including duplicate suffixes', function (): void {
    $converted = (new MarkdownRenderer)->convert(<<<'MD'
## Hello

### Nested

## Hello

#### Deep

##### Fifth

###### Sixth
MD);

    $headings = $converted['headings'];

    expect($headings)->toHaveCount(6)
        ->and($headings[0])->toMatchArray(['id' => 'hello', 'text' => 'Hello', 'level' => 2])
        ->and($headings[1])->toMatchArray(['id' => 'nested', 'text' => 'Nested', 'level' => 3])
        ->and($headings[2])->toMatchArray(['id' => 'hello-1', 'text' => 'Hello', 'level' => 2])
        ->and($headings[3])->toMatchArray(['id' => 'deep', 'text' => 'Deep', 'level' => 4])
        ->and($headings[4])->toMatchArray(['id' => 'fifth', 'text' => 'Fifth', 'level' => 5])
        ->and($headings[5])->toMatchArray(['id' => 'sixth', 'text' => 'Sixth', 'level' => 6]);
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

it('demotes step headings to h3 nested under the preceding h2', function (): void {
    $converted = (new MarkdownRenderer)->convert(<<<'MD'
## Steps

:::steps
## Install the package
Hi
:::
MD);

    expect($converted['html'])->toMatch('/<h3[^>]*id="install-the-package"/')
        ->and($converted['headings'])->toHaveCount(2)
        ->and($converted['headings'][0])->toMatchArray(['id' => 'steps', 'text' => 'Steps', 'level' => 2])
        ->and($converted['headings'][1])->toMatchArray(['id' => 'install-the-package', 'text' => 'Install the package', 'level' => 3]);

    $tree = (new HeadingExtractor)->nest($converted['headings']);

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['id'])->toBe('steps')
        ->and($tree[0]['children'])->toHaveCount(1)
        ->and($tree[0]['children'][0]['id'])->toBe('install-the-package')
        ->and($tree[0]['children'][0]['level'])->toBe(3);
});
