<?php

declare(strict_types=1);

use Vellum\Semantic\ModelDownloader;
use Vellum\Semantic\SemanticSet;
use Vellum\Tests\Semantic\FakeModel;

beforeEach(function (): void {
    $this->modelRoot = sys_get_temp_dir().'/vellum-model-'.$this->fixtureId();
    FakeModel::write($this->modelRoot.'/fake-model');
    config(['vellum.answers.model' => 'acme/fake-model', 'vellum.answers.model_path' => $this->modelRoot]);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nInstall the package with composer.\n\n## Open the site\n\nVisit the docs.\n");
    $this->writeDoc('billing.md', "---\ntitle: Billing\naccess: auth\n---\nsupercalifragilisticexpialidocious invoices.\n");
});

afterEach(function (): void {
    $this->deleteDirectory($this->modelRoot);
});

it('builds a semantic set in vellum:build and reports it', function (): void {
    $this->artisan('vellum:build')
        ->expectsOutputToContain('semantic: 3 sections (3 encoded)')
        ->assertSuccessful();

    $set = SemanticSet::load($this->cachePath().'/semantic');

    expect($set)->not->toBeNull()
        ->and(array_column($set->sections, 'id'))->toBe(['billing#', '#', '#open-the-site'])
        ->and(array_column($set->sections, 'group'))->toBe(['auth', 'guest', 'guest'])
        ->and($set->vocabBits)->toBe(4)
        ->and($set->sectionBits)->toBe(8)
        ->and($set->attribution)->toContain('acme/fake-model (MIT licence)');
});

it('never gives a guest the tokens of a gated page', function (): void {
    $this->artisan('vellum:build')->assertSuccessful();

    $set = SemanticSet::load($this->cachePath().'/semantic');
    $guest = SemanticSet::read($set->forGroups(['guest']));
    $member = SemanticSet::read($set->forGroups(['guest', 'auth']));
    $private = array_diff($member['tokens'], $guest['tokens']);

    expect($guest['ids'])->toBe(['#', '#open-the-site'])
        ->and($private)->not->toBeEmpty()
        ->and(implode(' ', $guest['tokens']))->not->toContain('##fra');

    foreach ($private as $token) {
        expect(str_contains('supercalifragilisticexpialidocious invoices billing', ltrim($token, '#')))->toBeTrue();
    }
});

it('only encodes what changed on the next build', function (): void {
    $this->artisan('vellum:build')->assertSuccessful();
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nInstall the package with composer today.\n\n## Open the site\n\nVisit the docs.\n");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('semantic: 3 sections (1 encoded)')
        ->assertSuccessful();
});

it('warns and carries on without the model', function (): void {
    config(['vellum.answers.model' => 'missing-model']);

    $this->artisan('vellum:build')
        ->expectsOutputToContain('search stays lexical. Run php artisan vellum:model.')
        ->assertSuccessful();

    expect(is_dir($this->cachePath().'/semantic'))->toBeFalse();
});

it('skips the semantic set when answers.semantic is false', function (): void {
    config(['vellum.answers.semantic' => false]);

    $this->artisan('vellum:build')->doesntExpectOutputToContain('semantic')->assertSuccessful();

    expect(is_dir($this->cachePath().'/semantic'))->toBeFalse();
});

it('reads the model for vellum:model from config', function (): void {
    $hub = sys_get_temp_dir().'/vellum-empty-hub-'.$this->fixtureId();
    mkdir($hub);
    $this->app->instance(ModelDownloader::class, new ModelDownloader('file://'.$hub));

    $this->artisan('vellum:model', ['--path' => sys_get_temp_dir().'/vellum-models-'.$this->fixtureId()])
        ->expectsOutputToContain('Cannot find the model acme/fake-model')
        ->assertFailed();

    rmdir($hub);
});

it('fails clearly when the model path cannot be created', function (): void {
    $this->artisan('vellum:model', ['--path' => '/nonexistent-'.$this->fixtureId()])
        ->expectsOutputToContain('Cannot create /nonexistent-')
        ->assertFailed();
});
