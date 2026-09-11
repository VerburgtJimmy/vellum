<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Normalises path segments and filenames into URL-safe slugs.
 */
final class Slug
{
    /**
     * Convert a filename or path segment into a lowercase dashed slug.
     */
    public static function from(string $value): string
    {
        $value = str_replace(['\\', '_'], ['/', '-'], $value);
        $value = preg_replace('/\s+/', '-', trim($value)) ?? '';
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\-\/]+/', '', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim($value, '-/');
    }

    /**
     * Build a document slug from a relative path like "guides/authentication.md".
     */
    public static function fromRelativePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = preg_replace('/\.md$/i', '', $relativePath) ?? $relativePath;

        if (str_ends_with($relativePath, '/index') || $relativePath === 'index') {
            $relativePath = substr($relativePath, 0, -strlen('index'));
            $relativePath = rtrim($relativePath, '/');
        }

        $parts = array_filter(
            explode('/', $relativePath),
            static fn (string $part): bool => $part !== '',
        );

        $parts = array_map(static fn (string $part): string => self::from($part), $parts);

        return implode('/', $parts);
    }
}
