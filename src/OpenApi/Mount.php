<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Support\Slug;
use Vellum\Support\VersionUrl;

/**
 * Where the reference pages live.
 *
 * Two shapes, because two kinds of project want this. A product with guides
 * wants the reference as another section of its docs. An API-first project
 * wants the reference to be the site, with nothing else in the sidebar.
 *
 * Compiled slugs carry the prefix either way, so one store holds both trees
 * and an endpoint can never collide with a Markdown page. Only the URL and
 * the sidebar differ.
 */
final class Mount
{
    public static function standalone(): bool
    {
        return config('vellum.openapi.mount') === 'standalone';
    }

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
     * Slugs are prefixed in both modes. Sharing the compiled store with the
     * Markdown tree is only safe if nothing can address the same slug twice.
     */
    public static function slugPrefix(): string
    {
        return self::prefix();
    }

    public static function href(string $slug, ?string $version = null): string
    {
        if (self::standalone()) {
            // The slug already opens with the prefix, which is the mount point.
            return '/'.trim($slug, '/');
        }

        return VersionUrl::href(
            (string) config('vellum.route.prefix', 'docs'),
            $slug,
            $version,
            (bool) config('vellum.versions.enabled') ? config('vellum.versions.latest') : null,
        );
    }

    /**
     * Whether a request slug belongs to the reference tree.
     */
    public static function owns(string $slug): bool
    {
        $slug = trim($slug, '/');
        $prefix = self::prefix();

        return $slug === $prefix || str_starts_with($slug, $prefix.'/');
    }
}
