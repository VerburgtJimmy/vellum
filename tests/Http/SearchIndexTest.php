<?php

declare(strict_types=1);

use Vellum\Search\SearchDriver;

it('has no index route of its own: the built-in driver searches in the browser', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello");
    $this->artisan('vellum:build')->assertSuccessful();

    // The old MiniSearch endpoints are gone; the answer index replaced them.
    $this->get('/docs/_vellum/search.json')->assertNotFound();
    $this->get('/docs/_vellum/answers.json')->assertOk();
});

it('answers the scout route for the scout driver only', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello");
    $this->artisan('vellum:build')->assertSuccessful();

    $this->get('/docs/_vellum/search.json?q=home')->assertNotFound();

    config()->set('vellum.search.driver', 'scout');
    $this->withoutExceptionHandling();

    // Scout is not installed here, so reaching it at all is what this shows.
    expect(fn () => $this->get('/docs/_vellum/search.json?q=home'))
        ->toThrow(RuntimeException::class, 'laravel/scout is not installed');
});

it('points live pages at the filtered answer index', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->artisan('vellum:build')->assertSuccessful();

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('_vellum/answers.json')
        ->toContain('data-vellum-search-driver="answers"')
        ->toContain('data-vellum-search-url="')
        ->not->toContain('_vellum/search');
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

it('takes builtin and its old name minisearch as the same driver', function (): void {
    config()->set('vellum.search.driver', 'minisearch');
    expect(SearchDriver::name())->toBe('builtin')
        ->and(SearchDriver::isScout())->toBeFalse();

    config()->set('vellum.search.driver', 'builtin');
    expect(SearchDriver::name())->toBe('builtin');

    config()->set('vellum.search.driver', 'scout');
    expect(SearchDriver::isScout())->toBeTrue();
});
