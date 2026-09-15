<?php

declare(strict_types=1);

use Vellum\Support\Color;
use Vellum\Support\Theme;

/**
 * Every shipped preset must clear WCAG AA (4.5:1) for the text a reader
 * actually has to read, in both light and dark mode. The values come from the
 * real stylesheets, so a palette edit either keeps the promise or fails here.
 */
function cssBlock(string $css, string $selector): array
{
    $position = strpos($css, $selector);

    if ($position === false) {
        return [];
    }

    $open = strpos($css, '{', $position);
    $close = strpos($css, '}', $open);
    $body = substr($css, $open + 1, $close - $open - 1);

    $tokens = [];

    foreach (explode(';', $body) as $declaration) {
        if (preg_match('/\s*(--[\w-]+)\s*:\s*(.+)/s', $declaration, $match) === 1) {
            $tokens[$match[1]] = trim($match[2]);
        }
    }

    return $tokens;
}

function presetTokens(string $preset, string $mode): array
{
    $base = file_get_contents(__DIR__.'/../../resources/css/vellum.css');
    $presets = file_get_contents(__DIR__.'/../../resources/css/presets.css');

    $root = $mode === 'dark'
        ? cssBlock($base, "\n.dark {")
        : cssBlock($base, ':root {');

    $selector = $mode === 'dark'
        ? sprintf('html[data-vellum-preset="%s"].dark {', $preset)
        : sprintf('html[data-vellum-preset="%s"] {', $preset);

    $own = $preset === 'neutral' ? [] : cssBlock($presets, $selector);
    $tokens = array_merge($root, $own);

    // The neutral accent is expressed as a hue variable; resolve it to its default.
    $hue = $root['--vellum-primary-hue'] ?? '0';

    return array_map(
        static fn (string $value): string => str_replace('var(--vellum-primary-hue)', $hue, $value),
        $tokens,
    );
}

dataset('preset modes', function (): Generator {
    foreach (Theme::PRESETS as $preset) {
        foreach (['light', 'dark'] as $mode) {
            yield "{$preset} {$mode}" => [$preset, $mode];
        }
    }
});

it('keeps readable text in every preset', function (string $preset, string $mode): void {
    $tokens = presetTokens($preset, $mode);

    $pairs = [
        'body text' => ['--foreground', '--background'],
        'secondary text' => ['--muted-foreground', '--background'],
        'link' => ['--primary', '--background'],
        'button label' => ['--primary-foreground', '--primary'],
    ];

    foreach ($pairs as $label => [$foregroundKey, $backgroundKey]) {
        $foreground = Color::parse($tokens[$foregroundKey] ?? '');
        $background = Color::parse($tokens[$backgroundKey] ?? '');

        expect($foreground)->not->toBeNull("{$preset} {$mode}: cannot read {$foregroundKey}");
        expect($background)->not->toBeNull("{$preset} {$mode}: cannot read {$backgroundKey}");

        $ratio = Color::contrast($foreground, $background);

        expect($ratio)->toBeGreaterThanOrEqual(
            4.5,
            sprintf('%s %s: %s is %.2f:1, needs 4.5:1', $preset, $mode, $label, $ratio),
        );
    }
})->with('preset modes');

it('keeps recessed surfaces visible against the page', function (string $preset, string $mode): void {
    $tokens = presetTokens($preset, $mode);

    // --card carries callouts, --muted carries table headers, tab strips and
    // step markers. If either matches the page, those components lose their shape.
    $surfaces = ['--card', '--muted'];

    foreach ($surfaces as $key) {
        $surface = Color::parse($tokens[$key] ?? '');
        $background = Color::parse($tokens['--background'] ?? '');

        expect($surface)->not->toBeNull("{$preset} {$mode}: cannot read {$key}");
        expect($background)->not->toBeNull("{$preset} {$mode}: cannot read --background");

        $ratio = Color::contrast($surface, $background);

        expect($ratio)->toBeGreaterThanOrEqual(
            1.04,
            sprintf('%s %s: %s is %.3f against the page, too close to read as a surface', $preset, $mode, $key, $ratio),
        );
    }
})->with('preset modes');
