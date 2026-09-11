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
        ->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
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
        ->toContain('/vendor/vellum/vellum.js');
});
