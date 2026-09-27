<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Small string helpers used across content parsing.
 */
final class Str
{
    /**
     * Convert a kebab or snake filename into Title Case words.
     */
    public static function titleCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Extract the first Markdown ATX heading (# Title) from a body, if present.
     */
    public static function firstHeading(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * Drop a "# Title" line that opens the body.
     *
     * Used when the title falls back to that heading: the layout renders its own
     * h1, so leaving it would ship two. Only a leading heading is removed, so a
     * "# " line further down (or inside a fence) is left alone.
     */
    public static function withoutLeadingHeading(string $markdown): string
    {
        return preg_replace('/\A\s*#[ \t]+[^\r\n]*(?:\r?\n)?/', '', $markdown, 1) ?? $markdown;
    }
}
