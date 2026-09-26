<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nInstall the package.\n");
    $this->writeDoc('billing.md', "---\ntitle: Billing\naccess: auth\naliases:\n  - Supercalifragilistic invoices\n---\nRefunds take five days, supercalifragilisticexpialidocious.\n");
    $this->artisan('vellum:build')->assertSuccessful();
});

it('serves a guest only what a guest may read', function (): void {
    $response = $this->get('/docs/_vellum/answers.json')->assertOk();
    $payload = $response->json();
    $body = (string) $response->getContent();

    expect(array_column($payload['sections'], 'id'))->toBe(['#'])
        ->and($payload)->not->toHaveKey('threshold')
        ->and($payload['synonyms'])->toContain(['dark mode', 'dark theme', 'night mode'])
        ->and($body)->not->toContain('Refunds')
        ->and($body)->not->toContain('Supercalifragilistic')
        ->and($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->headers->get('cache-control'))->toContain('private');
});

it('serves a signed-in reader the gated section as well', function (): void {
    $payload = $this->actingAs(new User)->get('/docs/_vellum/answers.json')->assertOk()->json();

    expect(array_column($payload['sections'], 'id'))->toContain('billing#')
        ->and($payload['synonyms'])->toContain(['Billing', 'Supercalifragilistic invoices']);
});

it('answers 304 when the reader already has the file', function (): void {
    $etag = $this->get('/docs/_vellum/answers.json')->assertOk()->headers->get('etag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => $etag])->get('/docs/_vellum/answers.json')->assertStatus(304);
});

it('gives a guest and a member different etags for the same url', function (): void {
    $guest = $this->get('/docs/_vellum/answers.json')->headers->get('etag');
    $member = $this->actingAs(new User)->get('/docs/_vellum/answers.json')->headers->get('etag');

    expect($guest)->not->toBe($member);
});

it('is not there when search is turned off', function (): void {
    config(['vellum.search.enabled' => false]);

    $this->get('/docs/_vellum/answers.json')->assertNotFound();
});

it('404s before the index is built', function (): void {
    $this->artisan('vellum:clear')->assertSuccessful();

    $this->get('/docs/_vellum/answers.json')->assertNotFound();
});

it('searches as json, in what the caller may see', function (): void {
    $guest = $this->get('/docs/_vellum/search?q=install+the+package')->assertOk();
    $payload = $guest->json();

    expect($payload['query'])->toBe('install the package')
        ->and(array_column($payload['results'], 'url'))->toBe(['/docs'])
        ->and($guest->headers->get('content-type'))->toContain('application/json')
        ->and($guest->headers->get('cache-control'))->toContain('private');

    $member = $this->actingAs(new User)->get('/docs/_vellum/search?q=refunds')->assertOk()->json();

    expect(array_column($member['results'], 'url'))->toContain('/docs/billing');
});

it('never gives a guest a gated page', function (): void {
    $response = $this->get('/docs/_vellum/search?q=how+long+do+refunds+take');

    expect((string) $response->getContent())->not->toContain('Refunds')
        ->and(array_column($response->json('results'), 'url'))->not->toContain('/docs/billing');
});

it('wants a query, and can be turned off', function (): void {
    $this->get('/docs/_vellum/search')->assertStatus(400);
    $this->get('/docs/_vellum/search?q=+')->assertStatus(400);

    config(['vellum.agents.search' => false]);
    $this->get('/docs/_vellum/search?q=install')->assertNotFound();
});

it('returns the top sections with their url, heading, passage and date', function (): void {
    $payload = $this->get('/docs/_vellum/search?q=install')->assertOk()->json();

    expect($payload)->toHaveKeys(['query', 'results'])
        ->and($payload)->not->toHaveKey('answer')
        ->and(array_keys($payload['results'][0]))->toBe(['url', 'title', 'heading', 'passage', 'updated', 'score']);
});
