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
