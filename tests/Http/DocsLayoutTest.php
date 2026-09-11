<?php

declare(strict_types=1);
use Vellum\Support\Assets;

it('renders docs chrome markers on a document page', function (): void {
    $this->writeDoc('index.md', <<<'MD'
---
title: Welcome
description: Docs home
---
## Setup

Hello **docs**
MD);

    $html = $this->get('/docs')
        ->assertOk()
        ->assertSee('data-vellum-header', false)
        ->assertSee('data-vellum-sidebar', false)
        ->assertSee('data-vellum-toc', false)
        ->assertSee('data-vellum-breadcrumb', false)
        ->assertSee('data-vellum-article', false)
        ->assertSee('vellum-prose', false)
        ->assertSee('Welcome', false)
        ->getContent();

    expect($html)
        ->toContain('rel="icon"')
        ->toContain('data-vellum-search="')
        ->toContain('/vendor/vellum/vellum.js')
        ->toContain('/vendor/vellum/vellum.css');
});

it('renders the branded 404 page for missing documents', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/missing-page')
        ->assertNotFound()
        ->assertSee('data-vellum-404', false)
        ->assertSee('Page not found', false)
        ->assertSee('Back to docs', false);
});

it('includes the search dialog when search is enabled', function (): void {
    config()->set('vellum.search.enabled', true);
    config()->set('vellum.search.hotkey', 'k');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')
        ->assertOk()
        ->assertSee('data-vellum-search', false)
        ->assertSee('data-vellum-dialog', false)
        ->assertSee('data-vellum-search-trigger', false)
        ->getContent();

    expect($html)
        ->toContain('data-vellum-search-hotkey="k"')
        ->toContain('x-data="vellumSearchHotkey')
        ->toContain('data-vellum-dialog');
});

it('binds the search hotkey in the main bundle before any lazy chunk loads', function (): void {
    $js = file_get_contents(Assets::jsPath());

    expect($js)->not->toBeFalse()
        ->and($js)->toContain('vellumSearchHotkey')
        ->and($js)->toContain('addEventListener')
        ->and($js)->toContain('metaKey')
        ->and($js)->toContain('ctrlKey')
        ->and($js)->toContain('preventDefault')
        ->and($js)->toContain('"keydown"')
        ->and($js)->toContain('pointerdown')
        ->and($js)->toContain('touchstart')
        ->and($js)->toContain('VellumSearch')
        ->and($js)->toContain('VellumFocus')
        ->and($js)->toContain('VellumAnchor');
});

it('exposes keyboard-complete theme toggle markup', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-theme-toggle')
        ->toContain('aria-haspopup="menu"')
        ->toContain(':aria-expanded="open.toString()"')
        ->toContain('role="menu"')
        ->toContain('role="menuitem"')
        ->toContain('closeMenu()')
        ->toContain('onMenuKeydown');
});

it('disables motion for reduced-motion users in css', function (): void {
    $css = file_get_contents(Assets::cssPath());

    expect($css)->not->toBeFalse()
        ->and($css)->toContain('prefers-reduced-motion')
        ->and($css)->toContain('transition-duration');
});

it('hides search ui when search is disabled', function (): void {
    config()->set('vellum.search.enabled', false);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs')
        ->assertOk()
        ->assertDontSee('data-vellum-search-trigger', false);
});

it('uses a sheet dialog for the mobile sidebar', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-mobile-sidebar')
        ->toContain('data-vellum-dialog')
        ->toContain('data-vellum-dialog-variant="sheet"')
        ->toContain('x-on:click="close()"');
});

it('renders prev and next pagination with prefetch hooks', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'a', 'b']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nH");
    $this->writeDoc('a.md', "---\ntitle: Alpha\n---\nA");
    $this->writeDoc('b.md', "---\ntitle: Beta\n---\nB");

    $this->get('/docs/a')
        ->assertOk()
        ->assertSee('data-vellum-pagination', false)
        ->assertSee('data-vellum-pagination-prev', false)
        ->assertSee('data-vellum-pagination-next', false)
        ->assertSee('Home', false)
        ->assertSee('Beta', false)
        ->assertSee('vellumPrefetchHover', false);
});

it('hides the desktop toc when the page is full-width', function (): void {
    $this->writeDoc('wide.md', <<<'MD'
---
title: Wide
full: true
---
## Section

Content
MD);

    $html = $this->get('/docs/wide')->assertOk()->getContent();

    expect($html)
        ->not->toContain('data-vellum-toc')
        ->not->toContain('data-vellum-toc-mobile');
});
