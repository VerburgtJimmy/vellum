<?php

declare(strict_types=1);

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

it('renders configured version labels in the switcher', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v1');
    config()->set('vellum.versions.list', ['next', 'v1']);
    config()->set('vellum.versions.labels', [
        'v1' => '1.x (LTS)',
        'next' => 'Next',
    ]);

    $this->writeDoc('v1/index.md', "---\ntitle: Home\n---\nStable");
    $this->writeDoc('next/index.md', "---\ntitle: Home\n---\nPreview");

    $this->get('/docs')
        ->assertOk()
        ->assertSee('data-vellum-version-switcher', false)
        ->assertSee('1.x (LTS)', false)
        ->assertSee('Next', false)
        ->assertDontSee('>Latest</', false);
});

it('compiles on demand when the cache is cold', function (): void {
    $this->writeDoc('cold.md', "---\ntitle: Cold\n---\nCached later");

    expect(is_file($this->cachePath().'/pages/cold.php'))->toBeFalse();

    $this->get('/docs/cold')->assertOk()->assertSee('Cold', false);

    expect(is_file($this->cachePath().'/pages/cold.php'))->toBeTrue();
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
    $this->registerFixtureComponents();
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

    $compiled = file_get_contents($this->cachePath().'/pages/index.php');
    expect($compiled)->toContain('VELLUMISLAND')
        ->and($compiled)->toContain('vellum::config')
        ->and($compiled)->not->toContain('Alpha');

    config()->set('vellum.name', 'Beta');

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Beta', false)
        ->assertDontSee('Alpha', false);
});

it('keeps a page called nav or manifest from overwriting the sidebar', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('nav.md', "---\ntitle: Nav\n---\nAbout navigation");
    $this->writeDoc('manifest.md', "---\ntitle: Manifest\n---\nAbout manifests");

    $this->artisan('vellum:build')->assertSuccessful();

    $this->get('/docs/nav')->assertOk()->assertSee('About navigation', false);
    $this->get('/docs/manifest')->assertOk()->assertSee('About manifests', false);
    $this->get('/docs')->assertOk()->assertSee('Nav', false)->assertSee('Manifest', false);
});

it('stops serving a page whose file was deleted once the docs are built again', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $secret = $this->writeDoc('secret.md', "---\ntitle: Secret\n---\nOld body");
    $this->writeDoc('renamed.md', "---\ntitle: Renamed\nslug: before\n---\nSlugged");

    $this->artisan('vellum:build')->assertSuccessful();
    $this->get('/docs/secret')->assertOk();
    $this->get('/docs/before')->assertOk();

    unlink($secret);
    $this->writeDoc('renamed.md', "---\ntitle: Renamed\nslug: after\n---\nSlugged");

    $this->artisan('vellum:build')->assertSuccessful();

    $this->get('/docs/secret')->assertNotFound();
    $this->get('/docs/before')->assertNotFound();
    $this->get('/docs/after')->assertOk();
});

it('serves only the pages the build compiled outside local', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:build')->assertSuccessful();

    $this->writeDoc('late.md', "---\ntitle: Late\n---\nAdded after the build");

    $this->get('/docs/late')->assertNotFound();

    $this->artisan('vellum:build')->assertSuccessful();

    $this->get('/docs/late')->assertOk();
});

it('turns a javascript: href on a card or a meta.json link into #', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n::card[Directive](javascript:alert%28document.domain%29)\n\n<x-vellum::card href=\"javascript:alert(1)\" title=\"Island\" />\n\n::card[Fine](/docs/guide)");
    file_put_contents($this->docsPath().'/meta.json', json_encode([
        'pages' => ['index', ['title' => 'Sneaky', 'href' => 'JavaScript:alert(1)']],
    ], JSON_THROW_ON_ERROR));

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->not->toMatch('/href="\\s*javascript:/i')
        ->and($html)->toContain('href="/docs/guide"')
        ->and($html)->toContain('Sneaky');
});
