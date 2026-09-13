<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('serves the index document at /docs', function (): void {
    $this->writeDoc('index.md', <<<'MD'
---
title: Welcome
description: Docs home
---
Hello **docs**
MD);

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Welcome', false)
        ->assertSee('<strong>docs</strong>', false)
        ->assertSee('Skip to content', false);
});

it('serves nested documents by slug', function (): void {
    $this->writeDoc('guides/authentication.md', <<<'MD'
---
title: Authentication
---
## Setup
MD);

    $this->get('/docs/guides/authentication')
        ->assertOk()
        ->assertSee('Authentication', false)
        ->assertSee('id="setup"', false);
});

it('returns 404 for missing documents', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/missing-page')
        ->assertNotFound()
        ->assertSee('Page not found', false);
});

it('serves the latest version at unprefixed URLs and older versions under /docs/{version}', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/guides/auth.md', "---\ntitle: Auth\n---\nV2");
    $this->writeDoc('v1/guides/auth.md', "---\ntitle: Auth\n---\nV1");

    $this->get('/docs/guides/auth')
        ->assertOk()
        ->assertSee('V2', false);

    $this->get('/docs/v2/guides/auth')
        ->assertRedirect('/docs/guides/auth')
        ->assertStatus(301);

    $this->get('/docs/v1/guides/auth')
        ->assertOk()
        ->assertSee('V1', false);
});

it('renders the version switcher when versions are enabled', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nV2");
    $this->writeDoc('v1/index.md', "---\ntitle: Home\n---\nV1");

    $this->get('/docs')
        ->assertOk()
        ->assertSee('data-vellum-version-switcher', false)
        ->assertSee('Latest', false)
        ->assertSee('aria-haspopup="menu"', false);
});

it('compiles on demand when the cache is cold', function (): void {
    $this->writeDoc('cold.md', "---\ntitle: Cold\n---\nCached later");

    expect(is_file($this->cachePath().'/cold.php'))->toBeFalse();

    $this->get('/docs/cold')->assertOk()->assertSee('Cold', false);

    expect(is_file($this->cachePath().'/cold.php'))->toBeTrue();
});

it('serves raw markdown for the current page', function (): void {
    $this->writeDoc('guides/one.md', <<<'MD'
---
title: One
---
Body of one
MD);

    $this->get('/docs/_vellum/raw/guides/one.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('title: One', false)
        ->assertSee('Body of one', false);
});

it('serves raw markdown for the index page', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nIndex body");

    $this->get('/docs/_vellum/raw/index.md')
        ->assertOk()
        ->assertSee('Index body', false);
});

it('renders nested markdown components on a document page', function (): void {
    Blade::anonymousComponentPath(__DIR__.'/../fixtures/components');
    config()->set('vellum.components.namespaces', ['vellum', '']);

    $this->writeDoc('index.md', <<<'MD'
---
title: Home
---
<x-card>
<x-alert type="ok">Hello **docs**</x-alert>
</x-card>
MD);

    $this->get('/docs')
        ->assertOk()
        ->assertSee('data-test-card', false)
        ->assertSee('data-test-alert', false)
        ->assertSee('<strong>docs</strong>', false);
});

it('resolves nested value tags from the compiled island tree after config changes', function (): void {
    config()->set('vellum.components.allowlist.config', ['vellum.name']);
    config()->set('vellum.name', 'Alpha');

    $this->writeDoc('index.md', <<<'MD'
---
title: Home
---
Top <x-vellum::config key="vellum.name" />

:::tabs
::tab[Live]
Nested <x-vellum::config key="vellum.name" />
:::
MD);

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Alpha', false);

    $compiled = file_get_contents($this->cachePath().'/index.php');
    expect($compiled)->toContain('VELLUMISLAND')
        ->and($compiled)->toContain('vellum::config')
        ->and($compiled)->not->toContain('Alpha');

    config()->set('vellum.name', 'Beta');

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Beta', false)
        ->assertDontSee('Alpha', false);
});
