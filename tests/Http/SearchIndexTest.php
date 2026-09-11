<?php

declare(strict_types=1);

it('serves the search index with immutable cache headers', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello");
    $this->writeDoc('guide.md', "---\ntitle: Guide\n---\nGuide");

    $this->artisan('vellum:build')->assertSuccessful();

    $response = $this->get('/docs/_vellum/search.json')
        ->assertOk()
        ->assertJsonStructure(['documents' => [['id', 'title', 'description', 'content', 'url', 'headings']]]);

    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');

    /** @var array{search_hash: string} $manifest */
    $manifest = require $this->cachePath().'/manifest.php';

    $hashed = $this->get('/docs/_vellum/search-'.$manifest['search_hash'].'.json')
        ->assertOk()
        ->json('documents');

    expect(collect($hashed)->pluck('title')->all())->toContain('Home', 'Guide');
});

it('resolves hashed search indexes across versions', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/index.md', "---\ntitle: V2\n---\nTwo");
    $this->writeDoc('v1/index.md', "---\ntitle: V1\n---\nOne");

    $this->artisan('vellum:build')->assertSuccessful();

    /** @var array{search_hash: string} $v1Manifest */
    $v1Manifest = require $this->cachePath().'/v1/manifest.php';

    $documents = $this->get('/docs/_vellum/search-'.$v1Manifest['search_hash'].'.json')
        ->assertOk()
        ->json('documents');

    expect(collect($documents)->pluck('title')->all())->toContain('V1')
        ->and(collect($documents)->pluck('title')->all())->not->toContain('V2');

    $latest = $this->get('/docs/_vellum/search.json')
        ->assertOk()
        ->json('documents');

    expect(collect($latest)->pluck('title')->all())->toContain('V2');
});

it('writes nav.php during build', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'a']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nH");
    $this->writeDoc('a.md', "---\ntitle: A\n---\nA");

    $this->artisan('vellum:build')->assertSuccessful();

    expect(is_file($this->cachePath().'/nav.php'))->toBeTrue();

    $nav = require $this->cachePath().'/nav.php';

    expect($nav[0]['slug'])->toBe('')
        ->and($nav[1]['slug'])->toBe('a');
});
