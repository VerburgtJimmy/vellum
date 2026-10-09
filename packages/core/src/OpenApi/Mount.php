<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Support\Slug;

/**
 * Where the reference pages live among the docs.
 *
 * Every generated slug opens with one prefix, so the compiled store holds the
 * reference beside the Markdown pages and an endpoint can never take the slug
 * of a page someone wrote.
 */
final class Mount
{
    /**
     * The path segment the reference lives under, in URLs and in slugs.
     */
    public static function prefix(): string
    {
        $prefix = config('vellum.openapi.prefix', 'api');
        $prefix = is_string($prefix) ? Slug::from(trim($prefix, '/')) : '';

        return $prefix === '' ? 'api' : $prefix;
    }

    /**
     * Whether a slug belongs to the reference.
     */
    public static function owns(string $slug): bool
    {
        $slug = trim($slug, '/');
        $prefix = self::prefix();

        return $slug === $prefix || str_starts_with($slug, $prefix.'/');
    }

    /**
     * Whether the reference has a sidebar to itself. Otherwise it is one more
     * folder at the end of the docs' own.
     */
    public static function separate(): bool
    {
        return config('vellum.openapi.sidebar', 'separate') !== 'docs';
    }
}
