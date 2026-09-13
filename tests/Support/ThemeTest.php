<?php

declare(strict_types=1);

use Vellum\Support\Theme;

it('resolves known fumadocs palettes', function (): void {
    config()->set('vellum.theme.preset', 'Ocean');

    expect(Theme::preset())->toBe('ocean');
});

it('falls back to neutral when the preset is missing or unknown', function (): void {
    config()->set('vellum.theme.preset', null);
    expect(Theme::preset())->toBe('neutral');

    config()->set('vellum.theme.preset', 'paper');
    expect(Theme::preset())->toBe('neutral');
});
