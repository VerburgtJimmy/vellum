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
