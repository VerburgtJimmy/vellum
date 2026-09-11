<?php

declare(strict_types=1);

use Vellum\Support\Assets;
use Vellum\Support\Cn;

it('merges conflicting tailwind classes', function (): void {
    expect(Cn::merge('px-2 py-1', 'px-4'))->toBe('py-1 px-4');
});

it('ignores null and empty class fragments', function (): void {
    expect(Cn::merge(null, '', 'text-sm'))->toBe('text-sm');
});

it('builds hashed asset urls', function (): void {
    expect(Assets::cssUrl())
        ->toContain('/vendor/vellum/vellum.css')
        ->toContain('v=');

    expect(Assets::jsUrl())
        ->toContain('/vendor/vellum/vellum.js')
        ->toContain('v=');

    expect(Assets::searchJsUrl())
        ->toContain('/vendor/vellum/vellum-search.js')
        ->toContain('v=');

    expect(Assets::anchorJsUrl())
        ->toContain('/vendor/vellum/vellum-anchor.js')
        ->toContain('v=');

    expect(Assets::focusJsUrl())
        ->toContain('/vendor/vellum/vellum-focus.js')
        ->toContain('v=');
});

it('serves compiled assets with immutable cache headers', function (): void {
    $css = $this->get(route('vellum.assets.css', ['v' => 'test']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

    expect($css->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');

    $js = $this->get(route('vellum.assets.js', ['v' => 'test']))
        ->assertOk();

    expect($js->headers->get('Cache-Control'))
        ->toContain('immutable');

    $this->get(route('vellum.assets.search', ['v' => 'test']))
        ->assertOk();

    $this->get(route('vellum.assets.anchor', ['v' => 'test']))
        ->assertOk();

    $this->get(route('vellum.assets.focus', ['v' => 'test']))
        ->assertOk();
});

it('keeps the floating-ui runtime in the lazy anchor chunk only', function (): void {
    $main = file_get_contents(Assets::jsPath());
    $chunk = file_get_contents(Assets::anchorJsPath());

    expect($main)->not->toBeFalse()
        ->and($chunk)->not->toBeFalse()
        ->and($main)->not->toContain('middleware')
        ->and($main)->not->toContain('clientWidth')
        ->and($chunk)->toContain('x-anchor')
        ->and($chunk)->toContain('floating')
        ->and($chunk)->toContain('middleware')
        ->and($chunk)->toContain('clientWidth')
        ->and(strlen($chunk))->toBeGreaterThan(15_000);
});

it('renders popover and tooltip markup with x-anchor directives', function (): void {
    $html = view('vellum::pages.ui-demo')->render();

    expect($html)
        ->toContain('x-data="vellumPopover')
        ->toContain('x-data="vellumTooltip')
        ->toContain('x-anchor.offset.8')
        ->toContain('x-anchor.offset.6')
        ->toContain('data-vellum-anchor=')
        ->toContain('/vendor/vellum/vellum-anchor.js')
        ->toContain('data-vellum-focus=')
        ->toContain('/vendor/vellum/vellum-focus.js')
        ->toContain('x-on:click="close()"')
        ->toContain('x-data="vellumDialog');
});

it('serves the ui demo only in the local environment', function (): void {
    $this->forceAppEnvironment = 'local';
    $this->refreshApplication();

    expect(app()->environment('local'))->toBeTrue();

    $this->get('/docs/_vellum/demo')
        ->assertOk()
        ->assertSee('data-vellum-button', false)
        ->assertSee('UI primitives', false);

    $this->forceAppEnvironment = null;
    $this->refreshApplication();
});

it('hides the ui demo outside the local environment', function (): void {
    $this->forceAppEnvironment = 'production';
    $this->refreshApplication();

    expect(app()->environment('local'))->toBeFalse();

    $this->get('/docs/_vellum/demo')->assertNotFound();

    $this->forceAppEnvironment = null;
    $this->refreshApplication();
});

it('renders every ui primitive marker on the demo page', function (): void {
    $html = view('vellum::pages.ui-demo')->render();

    expect($html)
        ->toContain('data-vellum-button')
        ->toContain('data-vellum-collapsible')
        ->toContain('data-vellum-tabs')
        ->toContain('data-vellum-tabs-list')
        ->toContain('data-vellum-tabs-trigger')
        ->toContain('data-vellum-tabs-content')
        ->toContain('data-vellum-accordion')
        ->toContain('data-vellum-accordion-item')
        ->toContain('data-vellum-dialog')
        ->toContain('data-vellum-popover')
        ->toContain('data-vellum-tooltip')
        ->toContain('data-vellum-scroll-area')
        ->toContain('data-vellum-badge')
        ->toContain('data-vellum-separator')
        ->toContain('data-vellum-kbd')
        ->toContain('Skip to content')
        ->toContain('/vendor/vellum/vellum.css')
        ->toContain('/vendor/vellum/vellum.js')
        ->toContain('data-vellum-search=')
        ->toContain('/vendor/vellum/vellum-search.js');
});
