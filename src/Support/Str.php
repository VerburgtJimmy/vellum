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
}
