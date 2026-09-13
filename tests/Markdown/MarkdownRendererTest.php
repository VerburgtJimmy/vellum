<?php

declare(strict_types=1);

use Vellum\Markdown\MarkdownRenderer;

it('renders gfm tables', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
| A | B |
| --- | --- |
| 1 | 2 |
MD);

    expect($html)->toContain('<table>')
        ->and($html)->toContain('vellum-table')
        ->and($html)->toContain('<td>1</td>');
});

it('renders strikethrough and task lists', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
~~old~~

- [x] Done
- [ ] Todo
MD);

    expect($html)->toContain('<del>old</del>')
        ->and($html)->toContain('type="checkbox"');
});

it('adds heading anchors with ids and permalinks', function (): void {
    $html = (new MarkdownRenderer)->render("## Hello World\n");

    expect($html)->toContain('id="hello-world"')
        ->toContain('data-vellum-heading-copy')
        ->and($html)->toContain('vellum-heading-anchor')
        ->and($html)->toMatch('/<h2[^>]*id="hello-world"/');
});
