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

/**
 * The syntax palette, read from the stylesheet. One palette per mode, shared by
 * every preset, so it has to clear AA on every preset's code surface.
 *
 * @return array<string, string>
 */
function syntaxColours(string $mode): array
{
    $css = file_get_contents(__DIR__.'/../../resources/css/vellum.css');
    $colours = [];

    foreach (explode('}', $css) as $rule) {
        $brace = strpos($rule, '{');

        if ($brace === false) {
            continue;
        }

        $selector = substr($rule, 0, $brace);

        if (! str_contains($selector, '.hl-')) {
            continue;
        }

        if (str_contains($selector, '.dark') !== ($mode === 'dark')) {
            continue;
        }

        if (preg_match('/color:\s*(#[0-9a-fA-F]{3,8})/', substr($rule, $brace + 1), $colour) !== 1) {
            continue;
        }

        preg_match('/\.hl-([\w-]+)/', $selector, $token);
        $colours[$token[1]] = $colour[1];
    }

    return $colours;
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

it('moves every surface one way from the canvas', function (string $preset, string $mode): void {
    $tokens = presetTokens($preset, $mode);

    $background = Color::parse($tokens['--background'] ?? '');
    $card = Color::parse($tokens['--card'] ?? '');
    $muted = Color::parse($tokens['--muted'] ?? '');

    expect($background)->not->toBeNull()
        ->and($card)->not->toBeNull()
        ->and($muted)->not->toBeNull();

    // --card carries the sidebar, callouts and popovers. --muted carries code
    // blocks, table headers, tab strips and step markers. Both step away from
    // the canvas, in the same direction, with muted the further of the two.
    $canvas = Color::luminance($background);
    $first = Color::luminance($card);
    $second = Color::luminance($muted);

    if ($mode === 'light') {
        expect($first)->toBeLessThan($canvas, "{$preset} light: --card should be darker than the canvas");
        expect($second)->toBeLessThan($first, "{$preset} light: --muted should be darker than --card");
    } else {
        expect($first)->toBeGreaterThan($canvas, "{$preset} dark: --card should be lighter than the canvas");
        expect($second)->toBeGreaterThan($first, "{$preset} dark: --muted should be lighter than --card");
    }

    foreach (['--card' => $card, '--muted' => $muted] as $key => $surface) {
        $ratio = Color::contrast($surface, $background);

        expect($ratio)->toBeGreaterThanOrEqual(
            1.04,
            sprintf('%s %s: %s is %.3f against the canvas, too close to read as a surface', $preset, $mode, $key, $ratio),
        );
    }
})->with('preset modes');

it('keeps syntax highlighting readable on every code surface', function (string $preset, string $mode): void {
    $surface = Color::parse(presetTokens($preset, $mode)['--muted'] ?? '');
    $colours = syntaxColours($mode);

    expect($surface)->not->toBeNull()
        ->and($colours)->not->toBeEmpty();

    foreach ($colours as $token => $hex) {
        $ratio = Color::contrast(Color::parse($hex), $surface);

        expect($ratio)->toBeGreaterThanOrEqual(
            4.5,
            sprintf('%s %s: .hl-%s (%s) is %.2f:1 on the code surface, needs 4.5:1', $preset, $mode, $token, $hex, $ratio),
        );
    }
})->with('preset modes');

/**
 * Method and status tones. They are not preset-scoped: GET is the same green
 * on every palette, the trade the callout accents already make. The canvas
 * underneath them is not, which is why this runs over every preset.
 *
 * @return array<string, string>
 */
function apiTones(string $mode): array
{
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/vellum.css');

    return cssBlock($css, $mode === 'dark' ? '.dark .vellum-api-operation {' : "\n.vellum-api-operation {");
}

it('keeps method and status badges readable on every canvas', function (string $preset, string $mode): void {
    $tokens = presetTokens($preset, $mode);
    $background = Color::parse($tokens['--background'] ?? '');
    $tones = apiTones($mode);

    expect($background)->not->toBeNull()
        ->and($tones)->toHaveKeys(['--vellum-api-get', '--vellum-api-post', '--vellum-api-put', '--vellum-api-delete']);

    foreach ($tones as $name => $value) {
        $tone = Color::parse($value);

        expect($tone)->not->toBeNull("{$preset} {$mode}: cannot read {$name}");

        // The badge sits on 12% of its own tone over the canvas. Compositing
        // in sRGB rather than oklch is a close approximation of the CSS
        // color-mix, and errs by a hair either way at this strength.
        $fill = Color::over([$tone[0], $tone[1], $tone[2], 0.12], $background);
        $ratio = Color::contrast($tone, $fill);

        expect($ratio)->toBeGreaterThanOrEqual(
            4.5,
            sprintf('%s %s: %s is %.2f:1 on its badge, needs 4.5:1', $preset, $mode, $name, $ratio),
        );
    }
})->with('preset modes');
