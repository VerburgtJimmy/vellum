<?php

declare(strict_types=1);

use Vellum\Content\FrontMatterParser;
use Vellum\Exceptions\InvalidFrontMatterException;

it('parses yaml frontmatter and body', function (): void {
    $parser = new FrontMatterParser;
    $result = $parser->parse(<<<'MD'
---
title: Installation
description: How to install
order: 2
---
# Hello

Body text.
MD);

    expect($result['matter'])->toMatchArray([
        'title' => 'Installation',
        'description' => 'How to install',
        'order' => 2,
    ])->and($result['body'])->toContain('# Hello');
});

it('returns empty matter when frontmatter is missing', function (): void {
    $parser = new FrontMatterParser;
    $result = $parser->parse("# Just a heading\n\nContent.");

    expect($result['matter'])->toBe([])
        ->and($result['body'])->toBe("# Just a heading\n\nContent.");
});

it('throws a clear exception naming the file for invalid yaml', function (): void {
    $parser = new FrontMatterParser;
    $path = '/tmp/broken-doc.md';

    $parser->parse(<<<'MD'
---
title: [unclosed
---
Body
MD, $path);
})->throws(InvalidFrontMatterException::class, 'Invalid frontmatter in [/tmp/broken-doc.md]');

it('parses files from disk', function (): void {
    $path = $this->writeDoc('from-disk.md', <<<'MD'
---
title: From Disk
---
Contents
MD);

    $result = (new FrontMatterParser)->parseFile($path);

    expect($result['matter']['title'])->toBe('From Disk')
        ->and($result['body'])->toBe('Contents');
});
