<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;

it('hides auth-only pages from guests on the route, nav, and raw markdown', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nNope");

    $guest = $this->get('/docs')->assertOk()->getContent();

    expect($guest)->toContain('Home')
        ->and($guest)->not->toContain('Secret');

    $this->get('/docs/secret')
        ->assertNotFound()
        ->assertSee('Page not found', false);

    $this->get('/docs/_vellum/raw/secret.md')->assertNotFound();

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $this->get('/docs/secret')
        ->assertOk()
        ->assertSee('Secret', false)
        ->assertSee('Nope', false);

    $this->get('/docs/_vellum/raw/secret.md')
        ->assertOk()
        ->assertSee('Nope', false);
});

it('inherits folder access onto pages that do not set their own', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('billing/meta.json', json_encode(['access' => 'auth'], JSON_THROW_ON_ERROR));
    $this->writeDoc('billing/invoices.md', "---\ntitle: Invoices\n---\nDollars");

    $this->get('/docs/billing/invoices')->assertNotFound();

    $guestNav = $this->get('/docs')->assertOk()->getContent();
    expect($guestNav)->not->toContain('Invoices');

    $titles = collect($this->get('/docs/_vellum/search.json')->json('documents'))->pluck('title')->all();
    expect($titles)->toContain('Home')
        ->and($titles)->not->toContain('Invoices');

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $this->get('/docs/billing/invoices')
        ->assertOk()
        ->assertSee('Invoices', false)
        ->assertSee('Dollars', false);
});

it('renders a host component for each reader instead of caching the first', function (): void {
    $this->registerFixtureComponents();
    config()->set('vellum.components.namespaces', ['vellum', '']);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nSigned in as <x-whoami />");

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));
    $this->get('/docs')->assertOk()->assertSee('Ada', false);

    $this->app['auth']->forgetGuards();
    $this->get('/docs')->assertOk()->assertSee('nobody', false)->assertDontSee('Ada', false);
});
