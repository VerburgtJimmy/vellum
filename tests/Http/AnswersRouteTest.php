<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Vellum\Semantic\SemanticSet;
use Vellum\Tests\Semantic\FakeModel;

beforeEach(function (): void {
    $this->modelRoot = sys_get_temp_dir().'/vellum-route-'.$this->fixtureId();
    FakeModel::write($this->modelRoot.'/fake-model');
    config(['vellum.answers.model' => 'acme/fake-model', 'vellum.answers.model_path' => $this->modelRoot]);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nInstall the package.\n");
    $this->writeDoc('billing.md', "---\ntitle: Billing\naccess: auth\naliases:\n  - Supercalifragilistic invoices\n---\nRefunds take five days, supercalifragilisticexpialidocious.\n");
    $this->artisan('vellum:build')->assertSuccessful();
});

afterEach(function (): void {
    $this->deleteDirectory($this->modelRoot);
});

it('serves a guest only what a guest may read', function (): void {
    $response = $this->get('/docs/_vellum/answers.json')->assertOk();
    $payload = $response->json();
    $body = (string) $response->getContent();

    expect(array_column($payload['sections'], 'id'))->toBe(['#'])
        ->and($payload['threshold'])->toBe(0.65)
        ->and($payload['synonyms'])->toContain(['dark mode', 'dark theme', 'night mode', 'dark look'])
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

it('serves vectors a guest may have, and no token only a gated page uses', function (): void {
    $response = $this->get('/docs/_vellum/semantic.bin')->assertOk();
    $guest = SemanticSet::read((string) $response->getContent());
    $member = SemanticSet::read((string) $this->actingAs(new User)->get('/docs/_vellum/semantic.bin')->getContent());

    expect($guest['ids'])->toBe(['#'])
        ->and($member['ids'])->toContain('billing#')
        ->and(count($member['tokens']))->toBeGreaterThan(count($guest['tokens']))
        ->and(array_diff($member['tokens'], $guest['tokens']))->not->toBeEmpty()
        ->and($response->headers->get('content-type'))->toBe('application/octet-stream');
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

it('is not there when answers or the semantic file are turned off', function (): void {
    config(['vellum.answers.semantic' => false]);
    $this->get('/docs/_vellum/semantic.bin')->assertNotFound();
    $this->get('/docs/_vellum/answers.json')->assertOk();

    config(['vellum.answers.enabled' => false]);
    $this->get('/docs/_vellum/answers.json')->assertNotFound();
});

it('404s before the index is built', function (): void {
    $this->artisan('vellum:clear')->assertSuccessful();

    $this->get('/docs/_vellum/answers.json')->assertNotFound();
});
