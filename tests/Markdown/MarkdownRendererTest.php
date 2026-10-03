<?php

declare(strict_types=1);

use Vellum\Markdown\MarkdownRenderer;

it('gives each table cell its column heading, and the table its roles, for the stacked layout on phones', function (): void {
    $html = (new MarkdownRenderer)->render("| Key | Default value |\n| --- | --- |\n| `path` | **docs** |\n| `name` | App |");

    expect($html)->toContain('<th role="columnheader">Key</th>')
        ->and($html)->toContain('<td role="cell" data-label="Key"><code')
        ->and($html)->toContain('<td role="cell" data-label="Default value"><strong>docs</strong></td>')
        ->and(substr_count($html, 'role="row"'))->toBe(3);
});

it('marks an image on a line of its own, and leaves one inside a sentence alone', function (): void {
    $html = (new MarkdownRenderer)->render("![Chart](https://cdn.example.com/900x300.png)\n\nSee ![dot](https://cdn.example.com/10x10.png) here.\n\n![Chart](https://cdn.example.com/900x300.png \"Caption\")");

    expect(substr_count($html, 'data-vellum-image-block'))->toBe(2)
        ->and($html)->toMatch('/See <img[^>]*alt="dot"[^>]*height="10"[^>]*style="[^"]*" \/> here/')
        ->and($html)->toMatch('/<figure class="vellum-figure"><img[^>]*data-vellum-image-block=""/');
});

it('renders gfm tables', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
| A | B |
| --- | --- |
| 1 | 2 |
MD);

    expect($html)->toContain('<table role="table">')
        ->and($html)->toContain('vellum-table')
        ->and($html)->toMatch('/<td role="cell" data-label="\w+">1<\/td>/');
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
