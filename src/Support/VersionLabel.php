<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Switcher display names. Folders and URLs stay the list slug.
 */
final class VersionLabel
{
    /**
     * Label for one version folder: config override, else "Latest" for the latest slug, else the slug.
     */
    public static function for(string $version): string
    {
        $configured = config('vellum.versions.labels');

        if (is_array($configured)) {
            $custom = $configured[$version] ?? null;

            if (is_string($custom)) {
                $custom = trim($custom);
            }

            if (is_string($custom) && $custom !== '') {
                return $custom;
            }
        }

        $latest = config('vellum.versions.latest');

        return $version === $latest ? 'Latest' : $version;
    }

    /**
     * @param  list<string>  $versions
     * @return array<string, string>
     */
    public static function map(array $versions): array
    {
        $labels = [];

        foreach ($versions as $version) {
            $labels[$version] = self::for($version);
        }

        return $labels;
    }
}
