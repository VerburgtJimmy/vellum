<?php

declare(strict_types=1);
use Vellum\View\Assets;

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
        // Colour, not weight: a bolder entry is wider and shifts the list.
        ->not->toContain('hover:font-semibold')
        ->toContain('is-active text-foreground')
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

it('makes next the described card and previous a plain link, both labelled', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'a', 'b']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: Start here.\n---\nH");
    $this->writeDoc('a.md', "---\ntitle: Alpha\ndescription: The middle page.\n---\nA");
    $this->writeDoc('b.md', "---\ntitle: Beta\ndescription: The last page.\n---\nB");

    $html = (string) $this->get('/docs/a')->assertOk()->assertSee('vellumPrefetchHover', false)->getContent();
    $pager = substr($html, (int) strpos($html, 'data-vellum-pagination'));
    $previous = substr($pager, (int) strpos($pager, 'data-vellum-pagination-prev'), (int) strpos($pager, 'data-vellum-pagination-next') - (int) strpos($pager, 'data-vellum-pagination-prev'));
    $next = substr($pager, (int) strpos($pager, 'data-vellum-pagination-next'));

    expect($previous)->toContain('<span class="vellum-pagination-label">Previous</span>')
        ->and($previous)->toContain('Home')
        ->and($previous)->not->toContain('Start here.')
        ->and($next)->toContain('<span class="vellum-pagination-label">Next</span>')
        ->and($next)->toContain('Beta')
        ->and($next)->toContain('<span class="vellum-pagination-description">The last page.</span>');
});

it('names the section of a neighbouring page that is in another folder', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'guides', 'reference']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nH");
    $this->writeDoc('guides/meta.json', json_encode(['title' => 'Guides', 'pages' => ['one', 'two']], JSON_THROW_ON_ERROR));
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\n1");
    $this->writeDoc('guides/two.md', "---\ntitle: Two\n---\n2");
    $this->writeDoc('reference/meta.json', json_encode(['title' => 'Reference', 'pages' => ['api']], JSON_THROW_ON_ERROR));
    $this->writeDoc('reference/api.md', "---\ntitle: API\n---\nA");

    $two = (string) $this->get('/docs/guides/two')->assertOk()->getContent();

    // One is in the same folder as Two, so it is just "Previous"; API is not.
    expect($two)->toContain('<span class="vellum-pagination-label">Previous</span>')
        ->and($two)->toContain('<span class="vellum-pagination-label">Next · Reference</span>')
        ->and((string) $this->get('/docs')->getContent())->toContain('<span class="vellum-pagination-label">Next · Guides</span>')
        ->and((string) $this->get('/docs/guides/one')->getContent())->toMatch('/<span class="vellum-pagination-label">Previous<\/span>/');
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

it('shows only the link there is on the first and last page', function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'last']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nH");
    $this->writeDoc('last.md', "---\ntitle: Last\n---\nL");

    $home = $this->get('/docs')->assertOk()->getContent();
    $last = $this->get('/docs/last')->assertOk()->getContent();

    expect($home)
        ->toContain('data-vellum-pagination-next')
        ->not->toContain('data-vellum-pagination-prev')
        ->and($last)
        ->toContain('data-vellum-pagination-prev')
        ->not->toContain('data-vellum-pagination-next');
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

it('says when the page changed in the line under its title, with a machine-readable date', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: The start.\nupdated: 2026-09-17\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();
    $meta = substr($html, (int) strpos($html, 'data-vellum-page-meta'));

    expect($meta)->toContain('datetime="2026-09-17T00:00:00+00:00" title="September 17, 2026">Updated Sep 17, 2026</time>')
        ->and($meta)->toMatch('/data-vellum-page-changed class="vellum-page-changed" hidden>Changed since your last visit/')
        ->and(strpos($html, 'The start.'))->toBeLessThan(strpos($html, 'data-vellum-page-meta'))
        ->and(strpos($html, 'data-vellum-page-meta'))->toBeLessThan(strpos($html, 'data-vellum-page-actions'))
        ->and($html)->not->toContain('data-vellum-page-rule');
});

it('gives a reading time only to pages long enough to need one, not counting code', function (): void {
    $words = implode(' ', array_fill(0, 700, 'word'));
    $code = "```bash\n".implode(' ', array_fill(0, 2000, 'code'))."\n```";

    $this->writeDoc('long.md', "---\ntitle: Long\n---\n{$words}\n\n{$code}");
    $this->writeDoc('short.md', "---\ntitle: Short\n---\n{$code}");

    expect($this->get('/docs/long')->assertOk()->getContent())->toContain('<span>3 min read</span>')
        ->and($this->get('/docs/short')->assertOk()->getContent())->not->toContain('min read');
});

it('shows no last updated date rather than the file mtime', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    expect($this->get('/docs')->assertOk()->getContent())->not->toContain('data-vellum-updated');
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
        ->and($css)->toContain('#cf222e')
        ->and($css)->toContain('#ff7b72')
        ->and($css)->toContain('#79c0ff')
        ->and($css)->toContain('#0550ae')
        ->and($css)->toContain('vellum-callout-rail')
        ->and($css)->toMatch('/\.vellum-callout-kind\{[^}]*clip:/')
        ->and($css)->toMatch('/\.vellum-callout-glyph\{[^}]*fill:\s*var\(--vellum-callout-accent\)/')
        ->and($css)->toMatch('/scrollbar-width:\s*none/')
        ->and($css)->toMatch('/\.vellum-tabs\{[^}]*background:var\(--card\)/')
        ->and($css)->toMatch('/\.vellum-tabs-code \.vellum-code\{[^}]*background:var\(--muted\)/')
        ->and($css)->not->toContain('#191919')
        ->and($css)->toContain('input[type=checkbox]')
        // The tick is a mask filled with currentColor, so it follows
        // --muted-foreground rather than carrying a colour of its own.
        ->and($css)->toMatch('/input\[type=checkbox\]:checked\):before\{[^}]*background-color:currentColor/')
        ->and($css)->toMatch('/input\[type=checkbox\]:checked\):before\{[^}]*mask-image:var\(--vellum-check\)/')
        ->and($css)->not->toMatch('/--vellum-check:url\([^)]*fill=/')
        ->and($css)->toContain('muted-foreground:oklch(72% 0 0)')
        ->and($css)->toContain('vellum-toc-link')
        ->and($css)->toContain('.vellum-toc-link.is-active')
        ->and($css)->toContain('data-vellum-sidebar-peek')
        ->and($css)->not->toMatch('/\[data-vellum-page-row\]\{[^}]*justify-content:\s*center/')
        ->and($css)->toContain('data-vellum-sidebar-hotzone')
        ->and($css)->toMatch('/data-vellum-preset[=]["\']?ocean/')
        ->and($css)->toMatch('/data-vellum-preset[=]["\']?laravel/')
        ->and($css)->toContain('#e32c03')
        ->and($css)->toContain('#f53003')
        ->and($css)->not->toMatch('/data-vellum-preset[=]["\']?catppuccin/')
        ->and($css)->not->toContain('margin-inline: -1rem');
});

it('applies the laravel colour preset from config', function (): void {
    config()->set('vellum.theme.preset', 'laravel');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preset="laravel"');
});

it('applies a configured accent to every preset', function (): void {
    config()->set('vellum.theme.preset', 'ocean');
    config()->set('vellum.theme.accent', '#7c3aed');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('html[data-vellum-preset] { --primary: #7c3aed')
        ->and($html)->toContain('--primary-foreground: #ffffff')
        ->and($html)->toContain('html[data-vellum-preset].dark { --primary: #7c3aed');

    // The accent has to come after the stylesheet to beat the preset block.
    expect(strpos($html, 'html[data-vellum-preset] { --primary'))
        ->toBeGreaterThan(strpos($html, 'vellum.css'));
});

it('omits the accent block when none is configured', function (): void {
    config()->set('vellum.theme.accent', null);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    expect($this->get('/docs')->assertOk()->getContent())
        ->not->toContain('html[data-vellum-preset] { --primary');
});

it('falls back to neutral for an unknown colour preset', function (): void {
    config()->set('vellum.theme.preset', 'not-a-palette');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preset="neutral"');
});

it('marks only the header bar itself as the header, since the scrollspy measures that element', function (): void {
    config()->set('vellum.layout.search', 'header');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n## One\n\nText.\n");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    // Anything else carrying this attribute, such as the page wrapper, would
    // be taken for a header as tall as the page, hiding every heading.
    expect(preg_match_all('/\sdata-vellum-header(?=[\s>])/', $html))->toBe(1)
        ->and($html)->toMatch('/<header\s[^>]*data-vellum-header/');
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
        ->toContain('aria-label="Documentation version: v2"')
        ->toContain('aria-haspopup="menu"')
        ->toContain('aria-controls=')
        ->toContain('role="menu"')
        ->toContain('role="menuitem"')
        ->toContain('Home: 0')
        ->toContain('End: this.count - 1');
});

it('keeps tab persist in the shared Alpine helper', function (): void {
    $js = file_get_contents(Assets::jsPath());

    expect($js)->not->toBeFalse()
        ->and($js)->toContain('vellum-tabs-')
        ->and($js)->toContain('localStorage.setItem');
});

it('keeps the active tab label readable whatever the accent is', function (): void {
    $css = file_get_contents(Assets::cssPath());

    // The accent is configurable and may be very light, so it carries the
    // underline while the label stays on --foreground.
    expect($css)->not->toBeFalse()
        ->and($css)->toMatch('/\.vellum-tabs-trigger\[aria-selected=["\']?true["\']?\]\{[^}]*color:var\(--foreground\)/')
        ->and($css)->toMatch('/\.vellum-tabs-trigger\[aria-selected=["\']?true["\']?\]\{[^}]*border-bottom-color:var\(--primary\)/')
        ->and($css)->toMatch('/\.vellum-tabs-underline\{[^}]*background:var\(--primary\)/')
        // [;{] so this does not match border-bottom-color, which should be --primary.
        ->and($css)->not->toMatch('/\.vellum-tabs-trigger\[aria-selected=["\']?true["\']?\]\{[^}]*[;{]color:var\(--primary\)/');
});

it('lets a long token in inline code wrap rather than widen the page', function (): void {
    $css = file_get_contents(Assets::cssPath());

    // A URL in backticks has no break opportunity; without this it pushes the
    // document wider than a phone viewport.
    expect($css)->not->toBeFalse()
        ->and($css)->toMatch('/\[data-vellum-inline-code\]\{[^}]*overflow-wrap:anywhere/');
});

it('lights every heading whose section is on screen, not only the last one passed', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n## One\n\nA\n\n## Two\n\nB");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    // A set rather than a single id: three short sections in view means three
    // entries lit, which a single activeId cannot express.
    expect($html)->toContain('activeIds.includes(')
        ->and($html)->not->toContain('activeId === ');
});

it('ships a scroll spy that measures sections rather than heading elements', function (): void {
    $js = (string) file_get_contents(dirname(__DIR__, 2).'/resources/dist/vellum.js');

    // A heading scrolled off the top must keep its section lit, so the spy
    // works from section ranges and no longer observes the headings alone.
    expect($js)->toContain('visibleIds')
        ->and($js)->not->toContain('IntersectionObserver');
});

it('binds the search dialog only to state the dialog script defines', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = (string) $this->get('/docs')->assertOk()->getContent();
    $script = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/vellum.js');

    // The dialog's state lives in vellumSearchDialog(); a name the markup uses
    // that the script no longer defines is an Alpine error on every keystroke.
    preg_match('/function vellumSearchDialog\(\w+\) \{(.*?)\n\}\n/s', $script, $dialog);
    preg_match_all('/:aria-activedescendant="(\w+)\./', $html, $bound);

    expect($bound[1])->not->toBeEmpty();

    foreach ($bound[1] as $name) {
        expect($dialog[1] ?? '')->toMatch('/\b'.$name.'\b/');
    }
});

it('groups search results by page under a listbox that scrolls like the sidebar', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $html = (string) $this->get('/docs')->assertOk()->getContent();
    $script = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/vellum.js');
    preg_match('/function vellumSearchDialog\(\w+\) \{(.*?)\n\}\n/s', $script, $dialog);

    expect($html)->toMatch('/id="vellum-search-results"\s+class="vellum-scroll-area[^"]*"/')
        ->and($html)->toContain('x-for="group in groups"')
        ->and($html)->toContain('role="group" :aria-label="group.title"')
        ->and($html)->toContain('x-for="hit in group.hits"')
        ->and($html)->toContain(':id="\'vellum-search-option-\' + hit.index"');

    foreach (['groups', 'showingRecent', 'showRecent', 'remember', 'active', 'results'] as $name) {
        expect($dialog[1] ?? '')->toMatch('/\b'.$name.'\b/');
    }
});

it('gives the sidebar what its details need', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\nupdated: 2026-09-20\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nOne");

    $html = (string) $this->get('/docs/guides/one')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-nav ')
        ->and($html)->toContain('data-vellum-nav-updated="2026-09-20"')
        ->and($html)->toContain('data-vellum-nav-group')
        // Set before the first paint when arriving from another docs page.
        ->and($html)->toContain("sessionStorage.getItem('vellum-sidebar-marker')");
});

it('draws the table of contents as a window by default, or as a line', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n## One\n\nA\n\n## Two\n\nB\n");

    $window = (string) $this->get('/docs')->assertOk()->getContent();

    expect($window)->toContain('data-vellum-toc-style="window"')
        ->and($window)->toContain('data-vellum-toc-window')
        ->and($window)->toContain('data-vellum-toc-hover')
        ->and($window)->not->toContain('data-vellum-toc-track');

    config()->set('vellum.layout.toc', 'line');
    $line = (string) $this->get('/docs')->assertOk()->getContent();

    expect($line)->toContain('data-vellum-toc-style="line"')
        ->and($line)->toContain('data-vellum-toc-track')
        ->and($line)->not->toContain('data-vellum-toc-window')
        ->and($line)->not->toContain('data-vellum-toc-dot');
});

it('numbers step titles in the table of contents and joins consecutive steps', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n## Setup\n\n:::steps\n## Install\nx\n\n## Configure\ny\n:::\n\n## Next\nz\n");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toMatch('/data-vellum-toc-id="install"[^>]*data-vellum-toc-step="1"[^>]*data-vellum-toc-joined/s')
        ->and($html)->toMatch('/data-vellum-toc-id="configure"[^>]*data-vellum-toc-step="2"/s')
        ->and($html)->not->toMatch('/data-vellum-toc-id="configure"[^>]*data-vellum-toc-joined/s')
        ->and($html)->toContain('<span class="vellum-toc-step" aria-hidden="true">1</span>Install');
});
