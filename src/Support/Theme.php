<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Resolves docs colour presets from config.
 */
final class Theme
{
    /**
     * Fumadocs-named palettes. Neutral is the compiled :root default.
     *
     * @var list<string>
     */
    public const PRESETS = [
        'neutral',
        'black',
        'vitepress',
        'dusk',
        'catppuccin',
        'ocean',
        'purple',
        'solar',
        'emerald',
        'ruby',
        'aspen',
    ];

    public static function preset(): string
    {
        $preset = strtolower(trim((string) config('vellum.theme.preset', 'neutral')));

        return in_array($preset, self::PRESETS, true) ? $preset : 'neutral';
    }
}
