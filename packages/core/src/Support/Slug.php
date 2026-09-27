<?php

declare(strict_types=1);

namespace Vellum\Support;

use Illuminate\Support\Str;

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
     * Whether a request slug is safe to resolve against the content root.
     *
     * Rejects empty, "." and ".." segments, backslashes and null bytes, so a
     * request can never address a file outside the docs directory.
     */
    public static function isSafe(string $slug): bool
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return true;
        }

        if (str_contains($slug, "\0") || str_contains($slug, '\\')) {
            return false;
        }

        foreach (explode('/', $slug) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
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

        $parts = array_map(static fn (string $part): string => self::segment($part), $parts);

        return implode('/', $parts);
    }

    /**
     * One path segment as a slug. A name written entirely in a non-Latin
     * script has nothing left once reduced to a-z, and an empty segment would
     * turn guides/入门.md into guides, the folder's own index. Such a name
     * keeps its letters instead: guides/入门.
     */
    public static function segment(string $part): string
    {
        $slug = self::from($part);

        if ($slug !== '') {
            return $slug;
        }

        $unicode = Str::slug($part, '-', null);

        if ($unicode === '') {
            throw new \InvalidArgumentException("The file name [{$part}] has no letters or digits to build a URL from. Rename it, or set slug: in its frontmatter.");
        }

        return $unicode;
    }
}
