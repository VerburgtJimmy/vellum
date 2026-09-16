<?php

declare(strict_types=1);

use Vellum\Http\DocsView;

beforeEach(function (): void {
    config()->set('app.url', 'https://docs.example.com');
    config()->set('vellum.name', 'Vellum');
});

it('emits a canonical link and open graph tags for a page', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\ndescription: Ship it safely.\n---\nBody");

    $html = (string) $this->get('/docs/guides/deploy')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="https://docs.example.com/docs/guides/deploy">')
        ->toContain('<meta property="og:url" content="https://docs.example.com/docs/guides/deploy">')
        ->toContain('<meta property="og:title" content="Deploy · Vellum">')
        ->toContain('<meta property="og:description" content="Ship it safely.">')
        ->toContain('<meta property="og:site_name" content="Vellum">')
        ->toContain('<meta property="og:type" content="article">')
        ->toContain('<meta name="twitter:card" content="summary">')
        ->toContain('<meta name="twitter:title" content="Deploy · Vellum">');
});

it('points the index canonical at the docs root', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Vellum\n---\nHi");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('<link rel="canonical" href="https://docs.example.com/docs">');
});

it('does not repeat the site name when the page title already is it', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Vellum\n---\nHi");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('<title>Vellum</title>')
        ->and($html)->not->toContain('Vellum · Vellum');
});

it('still suffixes the site name on every other page', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Vellum\n---\nHi");
    $this->writeDoc('search.md', "---\ntitle: Search\n---\nBody");

    expect((string) $this->get('/docs/search')->assertOk()->getContent())
        ->toContain('<title>Search · Vellum</title>');
});

it('omits the canonical link when app.url is not an origin', function (string $url): void {
    config()->set('app.url', $url);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->not->toContain('rel="canonical"')
        ->and($html)->not->toContain('og:url');
})->with(['', '/', 'localhost', './docs']);

it('marks the 404 page noindex and gives it no canonical', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = (string) $this->get('/docs/nope')->assertNotFound()->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex">')
        ->and($html)->toContain('<title>Page not found · Vellum</title>')
        ->and($html)->not->toContain('rel="canonical"');
});

it('prefers the export base url for canonical when it names an origin', function (): void {
    config()->set('vellum.export.base_url', 'https://static.example.com');

    expect(DocsView::canonical('/docs/a', staticExport: true))
        ->toBe('https://static.example.com/docs/a')
        ->and(DocsView::canonical('/docs/a'))
        ->toBe('https://docs.example.com/docs/a');
});

it('falls back to app.url when the export base url is only a path', function (): void {
    config()->set('vellum.export.base_url', '/');

    expect(DocsView::canonical('/docs/a', staticExport: true))
        ->toBe('https://docs.example.com/docs/a');
});

it('collapses the title through the helper', function (string $title, string $expected): void {
    expect(DocsView::pageTitle($title))->toBe($expected);
})->with([
    ['Vellum', 'Vellum'],
    ['Deploy', 'Deploy · Vellum'],
    ['', 'Vellum'],
    ['  Vellum  ', 'Vellum'],
]);

it('does not version-prefix the changelog canonical when versions are off', function (): void {
    // A 'latest' left behind in config must not leak into URLs once the
    // feature is switched off: /docs/v2/changelog is not a route.
    config()->set('vellum.versions', [
        'enabled' => false,
        'latest' => 'v2',
        'list' => ['v2', 'v1'],
        'labels' => [],
    ]);

    $path = sys_get_temp_dir().'/vellum-tests/changelog-'.$this->fixtureId().'.md';
    file_put_contents($path, "# Changelog\n\n## [0.1.0] - 2026-09-11\n\n- First release\n");
    config()->set('vellum.changelog', ['path' => $path, 'unreleased' => false]);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = (string) $this->get('/docs/changelog')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="https://docs.example.com/docs/changelog">')
        ->not->toContain('/docs/v2/changelog');

    unlink($path);
});
