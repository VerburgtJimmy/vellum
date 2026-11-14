<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Site;

/**
 * Switcher display names, for the app's own site.
 *
 * @deprecated Use the versions of the site you have: `$site->versions->label()`,
 *             `kind()` and `labelsFor()`. Kept for views published before 0.10,
 *             and removed in 1.0.
 */
final class VersionLabel
{
    public static function for(string $version): string
    {
        return app(Site::class)->versions->label($version);
    }

    /**
     * @return 'latest'|'unreleased'|'older'
     */
    public static function kind(string $version): string
    {
        return app(Site::class)->versions->kind($version);
    }

    /**
     * @param  list<string>  $versions
     * @return array<string, string>
     */
    public static function map(array $versions): array
    {
        return app(Site::class)->versions->labelsFor($versions);
    }
}
