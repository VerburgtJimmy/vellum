<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Builds docs hrefs. The default (latest) version has no version segment.
 */
final class VersionUrl
{
    public static function href(string $routePrefix, string $slug, ?string $version, ?string $defaultVersion): string
    {
        $segment = $version !== null && $version !== '' && $version !== $defaultVersion
            ? $version
            : null;

        $parts = array_filter([
            trim($routePrefix, '/'),
            $segment,
            trim($slug, '/'),
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return '/'.implode('/', $parts);
    }
}
