<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Vellum\Http\LinkHeader;

it('points the page head at its raw markdown', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it");

    expect((string) $this->get('/docs/guides/deploy')->assertOk()->getContent())
        ->toContain('<link rel="alternate" type="text/markdown" href="http://localhost/docs/_vellum/raw/guides/deploy.md">')
        ->and((string) $this->get('/docs')->assertOk()->getContent())
        ->toContain('<link rel="alternate" type="text/markdown" href="http://localhost/docs/_vellum/raw/index.md">');
});

it('sends the raw markdown url as a Link header', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it");

    $this->get('/docs/guides/deploy')
        ->assertOk()
        ->assertHeader('Link', '<http://localhost/docs/_vellum/raw/guides/deploy.md>; rel="alternate"; type="text/markdown"');
});

it('does not point the 404 page at raw markdown', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $response = $this->get('/docs/missing')->assertNotFound();

    expect($response->headers->has('Link'))->toBeFalse()
        ->and((string) $response->getContent())->not->toContain('type="text/markdown"');
});

it('names the docs version on raw markdown when versions are on', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1'], 'labels' => []]);
    $source = "---\ntitle: Deploy\n---\nShip it\n";
    $this->writeDoc('v2/deploy.md', $source);
    $this->writeDoc('v1/deploy.md', "---\ntitle: Deploy\n---\nOld way\n");

    $latest = $this->get('/docs/_vellum/raw/deploy.md')
        ->assertOk()
        ->assertHeader('X-Vellum-Docs-Version', 'v2');

    $this->get('/docs/_vellum/raw/v1/deploy.md')
        ->assertOk()
        ->assertHeader('X-Vellum-Docs-Version', 'v1');

    // The body is the file as written, same as Copy Markdown.
    expect($latest->getContent())->toBe($source);
});

it('sends no version header when versions are off', function (): void {
    $this->writeDoc('deploy.md', "---\ntitle: Deploy\n---\nShip it\n");

    $this->get('/docs/_vellum/raw/deploy.md')
        ->assertOk()
        ->assertHeaderMissing('X-Vellum-Docs-Version');
});

it('declares the html page canonical on raw markdown', function (): void {
    config()->set('app.url', 'https://docs.example.com');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it");

    $this->get('/docs/_vellum/raw/guides/deploy.md')
        ->assertOk()
        ->assertHeader('Link', '<https://docs.example.com/docs/guides/deploy>; rel="canonical"');

    $this->get('/docs/_vellum/raw/index.md')
        ->assertHeader('Link', '<https://docs.example.com/docs>; rel="canonical"');

    // Negotiated Markdown is the same response.
    $this->get('/docs/guides/deploy', ['Accept' => 'text/markdown'])
        ->assertHeader('Link', '<https://docs.example.com/docs/guides/deploy>; rel="canonical"');
});

it('points the canonical at the page of the same version', function (): void {
    config()->set('app.url', 'https://docs.example.com');
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1'], 'labels' => []]);
    $this->writeDoc('v2/deploy.md', "---\ntitle: Deploy\n---\nNew way");
    $this->writeDoc('v1/deploy.md', "---\ntitle: Deploy\n---\nOld way");

    $this->get('/docs/_vellum/raw/deploy.md')
        ->assertHeader('Link', '<https://docs.example.com/docs/deploy>; rel="canonical"');
    $this->get('/docs/_vellum/raw/v1/deploy.md')
        ->assertHeader('Link', '<https://docs.example.com/docs/v1/deploy>; rel="canonical"');
});

it('keeps the raw canonical root-relative when app.url is not an origin', function (): void {
    config()->set('app.url', 'localhost');
    $this->writeDoc('deploy.md', "---\ntitle: Deploy\n---\nShip it");

    $this->get('/docs/_vellum/raw/deploy.md')
        ->assertHeader('Link', '</docs/deploy>; rel="canonical"');
});

it('sends Last-Modified from the page date', function (): void {
    $this->writeDoc('dated.md', "---\ntitle: Dated\nupdated: '2026-09-17T10:15:00+02:00'\n---\nBody");
    $this->writeDoc('day.md', "---\ntitle: Day\nupdated: 2026-09-17\n---\nBody");

    $this->get('/docs/_vellum/raw/dated.md')->assertHeader('Last-Modified', 'Thu, 17 Sep 2026 08:15:00 GMT');
    $this->get('/docs/_vellum/raw/day.md')->assertHeader('Last-Modified', 'Thu, 17 Sep 2026 00:00:00 GMT');
    $this->get('/docs/dated', ['Accept' => 'text/markdown'])->assertHeader('Last-Modified', 'Thu, 17 Sep 2026 08:15:00 GMT');
});

it('sends no Last-Modified for a page without a date', function (): void {
    $this->writeDoc('undated.md', "---\ntitle: Undated\n---\nBody");

    $this->get('/docs/_vellum/raw/undated.md')->assertOk()->assertHeaderMissing('Last-Modified');
});

it('sends each Link value on its own line', function (): void {
    $response = new Response('');

    LinkHeader::add($response, 'https://e.com/a.md', 'alternate', 'text/markdown');
    LinkHeader::add($response, 'https://e.com/a', 'canonical');

    expect($response->headers->all('Link'))->toBe([
        '<https://e.com/a.md>; rel="alternate"; type="text/markdown"',
        '<https://e.com/a>; rel="canonical"',
    ])->and(substr_count((string) $response->headers, 'Link: '))->toBe(2);
});
