<?php

declare(strict_types=1);

use Vellum\Support\Theme;

it('resolves a known palette regardless of case', function (): void {
    config()->set('vellum.theme.preset', 'Ocean');

    expect(Theme::preset())->toBe('ocean');
});

it('ships three named colour presets', function (): void {
    expect(Theme::PRESETS)->toHaveCount(3)
        ->and(Theme::PRESETS)->toBe(['neutral', 'ocean', 'laravel']);
});

it('falls back to neutral when the preset is missing or unknown', function (): void {
    config()->set('vellum.theme.preset', null);
    expect(Theme::preset())->toBe('neutral');

    config()->set('vellum.theme.preset', 'paper');
    expect(Theme::preset())->toBe('neutral');
});

it('recognises a preset removed in 0.5', function (): void {
    config()->set('vellum.theme.preset', 'catppuccin');

    expect(Theme::removedPreset())->toBe('catppuccin')
        ->and(Theme::preset())->toBe('neutral');

    config()->set('vellum.theme.preset', 'paper');

    expect(Theme::removedPreset())->toBeNull();
});

it('reads one accent colour for both modes', function (): void {
    config()->set('vellum.theme.accent', '#e32c03');

    expect(Theme::accent())->toBe([
        'light' => ['color' => '#e32c03', 'foreground' => '#ffffff'],
        'dark' => ['color' => '#e32c03', 'foreground' => '#ffffff'],
    ]);
});

it('reads a per-mode accent and picks a readable label', function (): void {
    config()->set('vellum.theme.accent', ['light' => '#1d4ed8', 'dark' => '#bfdbfe']);

    $accent = Theme::accent();

    expect($accent['light']['foreground'])->toBe('#ffffff')
        ->and($accent['dark']['foreground'])->toBe('#171717');
});

it('ignores an accent it cannot parse', function (): void {
    config()->set('vellum.theme.accent', 'rebeccapurple');
    expect(Theme::accent())->toBeNull();

    config()->set('vellum.theme.accent', null);
    expect(Theme::accent())->toBeNull();
});
