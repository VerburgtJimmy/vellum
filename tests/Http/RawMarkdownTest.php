<?php

declare(strict_types=1);

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
