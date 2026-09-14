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
        ->assertSee('data-vellum-sidebar', false)
        ->assertSee('data-vellum-toc', false)
        ->assertSee('data-vellum-breadcrumb', false)
        ->assertSee('data-vellum-article', false)
        ->assertSee('vellum-prose', false)
        ->assertSee('Welcome', false)
        ->getContent();

    expect($html)
        ->not->toContain('data-vellum-header')
        ->toContain('rel="icon"')
        ->toContain('data-vellum-search="')
        ->toContain('/vendor/vellum/vellum.js')
        ->toContain('/vendor/vellum/vellum.css')
        ->toContain('data-vellum-page-actions')
        ->toContain('Copy Markdown')
        ->toContain('data-vellum-sidebar-collapse')
        ->toContain('data-vellum-sidebar-pill')
        ->toContain('data-vellum-sidebar-hotzone')
        ->toContain('data-vellum-toc-popover-trigger')
        ->toContain('data-vellum-toc-popover-panel')
        ->toContain('bg-background/95 backdrop-blur')
        ->not->toContain('backdrop-blur-sm')
        ->toContain('role="progressbar"')
        ->toContain('placeholder="Search"')
        ->toContain('x-text="mod"')
        ->toContain('>Ctrl</span>')
        ->not->toContain('Built with Vellum')
        ->not->toContain('&copy;')
        ->not->toContain('mx-auto flex w-full max-w-[1400px]')
        ->not->toContain('justify-center gap-8')
        ->toContain('xl:px-8')
        ->toContain('data-vellum-page-row')
        ->toContain('vellum-toc-link')
        ->toContain('hover:font-semibold')
        ->toContain('is-active font-semibold text-foreground')
        ->toContain('data-vellum-toc-nav')
        ->toContain('viewBox="0 0 256 256"')
        ->toContain('data-vellum-preset="neutral"');
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
        ->toContain('data-vellum-dialog')
        ->toContain('data-vellum-dialog-variant="search"')
        ->toContain('placeholder="Search"')
        ->toContain('aria-label="Search documentation"')
        ->toContain('role="combobox"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('>ESC</button>')
        ->toContain('md:top-[calc(50%-250px)]')
        ->toContain('backdrop-blur-[4px]');
});

it('binds the search hotkey in the main bundle before any lazy chunk loads', function (): void {
    $js = file_get_contents(Assets::jsPath());

    expect($js)->not->toBeFalse()
        ->and($js)->toContain('vellumSearchHotkey')
        ->and($js)->toContain('showPeek')
        ->and($js)->toContain('peekLockedUntil')
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
        ->toContain('onMenuKeydown')
        ->toContain('bottom-full left-0 mb-1');
});

it('disables motion for reduced-motion users in css', function (): void {
    $css = file_get_contents(Assets::cssPath());

    expect($css)->not->toBeFalse()
        ->and($css)->toContain('prefers-reduced-motion')
        ->and($css)->toContain('transition-duration')
        ->and($css)->toContain('--vellum-sidebar-duration')
        ->and($css)->toContain('--vellum-sidebar-ease')
        ->and($css)->toContain('data-vellum-sheet-side')
        ->and($css)->toContain('data-vellum-sheet-overlay');
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
        ->toContain('inset-y-0 right-0')
        ->toContain('w-[85%]')
        ->toContain('max-w-[380px]')
        ->toContain('backdrop-blur-2xl')
        ->toContain('data-vellum-mobile-drawer')
        ->toContain('data-vellum-sheet-side="right"')
        ->toContain("entered && 'is-entered'")
        ->toContain('x-on:click="close()"');

    expect(strpos($html, 'data-vellum-brand'))->toBeLessThan(strpos($html, 'Open navigation'));
});

it('renders prev and next pagination with prefetch hooks', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'a', 'b']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: Start here.\n---\nH");
    $this->writeDoc('a.md', "---\ntitle: Alpha\ndescription: The middle page.\n---\nA");
    $this->writeDoc('b.md', "---\ntitle: Beta\ndescription: The last page.\n---\nB");

    $html = $this->get('/docs/a')
        ->assertOk()
        ->assertSee('data-vellum-pagination', false)
        ->assertSee('data-vellum-pagination-prev', false)
        ->assertSee('data-vellum-pagination-next', false)
        ->assertSee('Home', false)
        ->assertSee('Beta', false)
        ->assertSee('Start here.', false)
        ->assertSee('The last page.', false)
        ->assertSee('vellumPrefetchHover', false)
        ->getContent();

    expect($html)
        ->not->toContain('>Previous</')
        ->not->toContain('>Next</');
});

it('reserves the toc column when a page has no headings', function (): void {
    $this->writeDoc('plain.md', <<<'MD'
---
title: Plain
---
Just a paragraph.
MD);

    $html = $this->get('/docs/plain')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-toc-placeholder')
        ->not->toContain('On this page')
        ->not->toContain('data-vellum-toc-nav')
        ->not->toContain('data-vellum-toc-mobile');
});

it('makes a single prev or next card full width', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'last']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nH");
    $this->writeDoc('last.md', "---\ntitle: Last\n---\nL");

    $home = $this->get('/docs')->assertOk()->getContent();
    $last = $this->get('/docs/last')->assertOk()->getContent();

    expect($home)
        ->toContain('data-vellum-pagination-next')
        ->not->toContain('data-vellum-pagination-prev')
        ->not->toContain('sm:grid-cols-2')
        ->and($last)
        ->toContain('data-vellum-pagination-prev')
        ->not->toContain('data-vellum-pagination-next')
        ->not->toContain('sm:grid-cols-2');
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
        ->not->toContain('data-vellum-toc-mobile')
        ->not->toContain('data-vellum-toc-placeholder');
});

it('renders a header when search is placed in the header', function (): void {
    config()->set('vellum.layout.search', 'header');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-header')
        ->toContain('data-vellum-header-search')
        ->toContain('data-vellum-search-trigger-variant="header"')
        ->not->toContain('data-vellum-search-trigger-variant="sidebar"');
});

it('places search in the sidebar by default', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-search-trigger-variant="sidebar"')
        ->toContain('data-vellum-sidebar-footer')
        ->not->toContain('data-vellum-header');
});

it('moves last updated above the title and omits bottom page meta', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-updated')
        ->not->toContain('data-vellum-page-meta');
});

it('exposes a keyboard-complete open menu on page actions', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-open-menu')
        ->toContain('Open in ChatGPT')
        ->toContain('Open in Claude')
        ->toContain('View raw Markdown')
        ->toContain('role="menu"')
        ->toContain('closeMenu()')
        ->toContain('onMenuKeydown');
});

it('keeps line-number gutters unselectable in css', function (): void {
    $css = file_get_contents(Assets::cssPath());

    expect($css)->not->toBeFalse()
        ->and($css)->toMatch('/user-select:\s*none/')
        ->and($css)->toContain('counter-increment')
        ->and($css)->toContain('.hl-keyword')
        ->and($css)->toContain('#d73a49')
        ->and($css)->toContain('#f97583')
        ->and($css)->toContain('#79b8ff')
        ->and($css)->toContain('#005cc5')
        ->and($css)->toContain('vellum-callout-rail')
        ->and($css)->toMatch('/\.vellum-callout\{[^}]*align-items:\s*flex-start/')
        ->and($css)->toMatch('/\.vellum-callout-glyph\{[^}]*fill:\s*var\(--vellum-callout-accent\)/')
        ->and($css)->toMatch('/scrollbar-width:\s*none/')
        ->and($css)->toMatch('/\.dark \.vellum-tabs-code \.vellum-code\{[^}]*background:#191919/')
        ->and($css)->toContain('input[type=checkbox]')
        ->and($css)->toContain('oklch(0.72 0 0)')
        ->and($css)->toContain('vellum-toc-link')
        ->and($css)->toContain('.vellum-toc-link.is-active')
        ->and($css)->toContain('data-vellum-sidebar-peek')
        ->and($css)->not->toMatch('/\[data-vellum-page-row\]\{[^}]*justify-content:\s*center/')
        ->and($css)->toContain('data-vellum-sidebar-hotzone')
        ->and($css)->toMatch('/data-vellum-preset[=]["\']?ocean/')
        ->and($css)->not->toContain('margin-inline: -1rem');
});

it('applies a fumadocs colour preset from config', function (): void {
    config()->set('vellum.theme.preset', 'ocean');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preset="ocean"');
});

it('falls back to neutral for an unknown colour preset', function (): void {
    config()->set('vellum.theme.preset', 'not-a-palette');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preset="neutral"');
});

it('exposes accessible version switcher markup', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('v1/index.md', "---\ntitle: Home\n---\nOld");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-vellum-version-switcher')
        ->toContain('aria-label="Select documentation version"')
        ->toContain('aria-haspopup="menu"')
        ->toContain('aria-controls=')
        ->toContain('role="menu"')
        ->toContain('role="menuitem"')
        ->toContain("event.key === 'Home'")
        ->toContain("event.key === 'End'");
});

it('keeps tab persist in the shared Alpine helper', function (): void {
    $js = file_get_contents(Assets::jsPath());

    expect($js)->not->toBeFalse()
        ->and($js)->toContain('vellum-tabs-')
        ->and($js)->toContain('localStorage.setItem');
});
