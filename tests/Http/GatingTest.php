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

it('keeps a meta.json link inside a gated folder from guests', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('ops/meta.json', json_encode([
        'title' => 'Internal Ops',
        'access' => 'auth',
        'pages' => [['title' => 'On-call board', 'href' => 'https://grafana.corp.example/oncall']],
    ], JSON_THROW_ON_ERROR));

    $guest = $this->get('/docs')->assertOk()->getContent();

    expect($guest)->not->toContain('Internal Ops')
        ->and($guest)->not->toContain('On-call board')
        ->and($guest)->not->toContain('grafana.corp.example');

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $this->get('/docs')->assertOk()->assertSee('Internal Ops', false)->assertSee('On-call board', false);
});

it('takes folder access from _meta.md for a link, and lets a link set its own', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('ops/_meta.md', "---\naccess: auth\n---\n");
    $this->writeDoc('ops/meta.json', json_encode([
        'title' => 'Ops',
        'pages' => [
            ['title' => 'Private board', 'href' => 'https://grafana.corp.example/oncall'],
            ['title' => 'Status page', 'href' => 'https://status.example.com', 'access' => 'guest'],
        ],
    ], JSON_THROW_ON_ERROR));

    $this->get('/docs')->assertOk()
        ->assertSee('Status page', false)
        ->assertDontSee('Private board', false);
});

it('shows a link to a gated page only to readers who can open it', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('internal/plan.md', "---\ntitle: Plan\naccess: auth\n---\nSecret plan");
    file_put_contents($this->docsPath().'/meta.json', json_encode([
        'pages' => ['index', ['title' => 'Acquisition plan', 'slug' => 'internal/plan']],
    ], JSON_THROW_ON_ERROR));

    $this->get('/docs')->assertOk()
        ->assertDontSee('Acquisition plan', false)
        ->assertDontSee('/docs/internal/plan', false);

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $this->get('/docs')->assertOk()->assertSee('Acquisition plan', false);
});
