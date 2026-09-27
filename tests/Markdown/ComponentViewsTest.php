<?php

declare(strict_types=1);

use Vellum\Markdown\Islands\MarkdownPipeline;

it('renders tabs with the site\'s view in place of the plain one core ships', function (): void {
    $html = (new MarkdownPipeline)->render(":::tabs\n::tab[One]\nFirst\n::\n:::");

    expect($html)->toContain('vellumTabs(');
});

it('falls back to core for a component the site does not restyle', function (): void {
    expect((new MarkdownPipeline)->render(":::note\nHi\n:::"))->toContain('vellum-callout vellum-callout-note');
});

it('prefers a view the app published over the site\'s and core\'s', function (): void {
    $this->publishViews([
        'components/tabs.blade.php' => '<div class="app-tabs">{{ count($tabs) }}</div>',
        'components/callout.blade.php' => '<aside class="app-callout">{!! $slot !!}</aside>',
    ]);

    $html = (new MarkdownPipeline)->render(":::tabs\n::tab[One]\nFirst\n::\n:::\n\n<x-vellum::callout>Hi</x-vellum::callout>");

    expect($html)->toContain('<div class="app-tabs">1</div>')
        ->and($html)->toContain('<aside class="app-callout">');
});
