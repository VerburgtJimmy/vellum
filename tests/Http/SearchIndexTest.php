<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;

it('serves a filtered MiniSearch index from a package route', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello");
    $this->writeDoc('guide.md', "---\ntitle: Guide\n---\nGuide");

    $this->artisan('vellum:build')->assertSuccessful();

    /** @var array{search_hash: string} $manifest */
    $manifest = require $this->cachePath().'/manifest.php';

    $response = $this->get('/docs/_vellum/search.json')
        ->assertOk()
        ->assertJsonStructure(['driver', 'documents' => [['id', 'title', 'description', 'content', 'url', 'headings', 'access']]]);

    $etag = $response->headers->get('ETag');

    expect($response->json('driver'))->toBe('minisearch')
        ->and($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->and($response->headers->get('Cache-Control'))
        ->not->toContain('immutable')
        ->and($etag)->not->toBeEmpty()
        ->and($etag)->toContain($manifest['search_hash']);

    $this->withHeaders(['If-None-Match' => (string) $etag])
        ->get('/docs/_vellum/search.json')
        ->assertStatus(304);

    $this->flushHeaders();

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

    $v1 = $this->get('/docs/_vellum/search.json?version=v1')
        ->assertOk()
        ->json('documents');

    expect(collect($v1)->pluck('title')->all())->toContain('V1')
        ->and(collect($v1)->pluck('title')->all())->not->toContain('V2');

    $latestUrls = collect($latest)->pluck('url')->all();
    expect($latestUrls)->toContain('/docs')
        ->and($latestUrls)->not->toContain('/docs/v2');
});

it('points live pages at the filtered search route, not a hashed static file', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->artisan('vellum:build')->assertSuccessful();

    /** @var array{search_hash: string} $manifest */
    $manifest = require $this->cachePath().'/manifest.php';

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('_vellum/search.json')
        ->toContain('data-vellum-search-driver="minisearch"')
        ->toContain('data-vellum-search-url="')
        ->not->toContain('search-'.$manifest['search_hash'].'.json');
});

it('omits auth-only pages from the guest search index', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nNope");
    $this->artisan('vellum:build')->assertSuccessful();

    $guestResponse = $this->get('/docs/_vellum/search.json')->assertOk();
    $guestEtag = $guestResponse->headers->get('ETag');
    $guest = collect($guestResponse->json('documents'))
        ->pluck('title')
        ->all();

    expect($guest)->toContain('Home')
        ->and($guest)->not->toContain('Secret')
        ->and($guestEtag)->not->toBeEmpty();

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $authResponse = $this->withHeaders(['If-None-Match' => (string) $guestEtag])
        ->get('/docs/_vellum/search.json')
        ->assertOk();

    $auth = collect($authResponse->json('documents'))
        ->pluck('title')
        ->all();

    expect($auth)->toContain('Home', 'Secret')
        ->and($authResponse->headers->get('ETag'))->not->toBe($guestEtag);
});

it('fails when scout is configured but laravel/scout is not installed', function (): void {
    config()->set('vellum.search.driver', 'scout');

    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/docs/_vellum/search.json'))
        ->toThrow(RuntimeException::class, 'laravel/scout is not installed');
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
