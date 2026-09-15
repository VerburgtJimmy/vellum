<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Resolves docs colour presets and the configured accent.
 */
final class Theme
{
    /**
     * Shipped palettes. Neutral is the compiled :root default; the others are
     * deltas on top of it. Each one is contrast tested in both modes.
     *
     * @var list<string>
     */
    public const PRESETS = [
        'neutral',
        'ocean',
        'laravel',
    ];

    /**
     * Palettes removed in 0.5. They fall back to neutral, and vellum:build
     * says so once rather than silently changing the site's colours.
     *
     * @var list<string>
     */
    public const REMOVED = [
        'black',
        'vitepress',
        'dusk',
        'catppuccin',
        'purple',
        'solar',
        'emerald',
        'ruby',
        'aspen',
    ];

    public static function preset(): string
    {
        $preset = self::configuredPreset();

        return in_array($preset, self::PRESETS, true) ? $preset : 'neutral';
    }

    /**
     * The preset name as configured, whether or not it still exists.
     */
    public static function configuredPreset(): string
    {
        return strtolower(trim((string) config('vellum.theme.preset', 'neutral')));
    }

    public static function removedPreset(): ?string
    {
        $preset = self::configuredPreset();

        return in_array($preset, self::REMOVED, true) ? $preset : null;
    }

    /**
     * The configured accent, as a fill and a readable label per mode.
     *
     * `theme.accent` is either one colour used in both modes, or an array with
     * `light` and `dark` keys. Unparseable values are ignored.
     *
     * @return array<string, array{color: string, foreground: string}>|null
     */
    public static function accent(): ?array
    {
        /** @var mixed $configured */
        $configured = config('vellum.theme.accent');

        if (is_string($configured)) {
            $configured = ['light' => $configured, 'dark' => $configured];
        }

        if (! is_array($configured)) {
            return null;
        }

        $resolved = [];

        foreach (['light', 'dark'] as $mode) {
            /** @var mixed $value */
            $value = $configured[$mode] ?? null;

            if (! is_string($value) || trim($value) === '' || Color::parse($value) === null) {
                continue;
            }

            $value = trim($value);

            $resolved[$mode] = [
                'color' => $value,
                'foreground' => Color::readableOn($value),
            ];
        }

        return $resolved === [] ? null : $resolved;
    }
}
