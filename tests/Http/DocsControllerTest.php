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
    $this->get('/docs/missing-page')->assertNotFound();
});

it('redirects unversioned paths to the latest version', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/guides/auth.md', "---\ntitle: Auth\n---\nV2");

    $this->get('/docs/guides/auth')
        ->assertRedirect('/docs/v2/guides/auth');

    $this->get('/docs/v2/guides/auth')
        ->assertOk()
        ->assertSee('Auth', false);
});

it('compiles on demand when the cache is cold', function (): void {
    $this->writeDoc('cold.md', "---\ntitle: Cold\n---\nCached later");

    expect(is_file($this->cachePath().'/cold.php'))->toBeFalse();

    $this->get('/docs/cold')->assertOk()->assertSee('Cold', false);

    expect(is_file($this->cachePath().'/cold.php'))->toBeTrue();
});
