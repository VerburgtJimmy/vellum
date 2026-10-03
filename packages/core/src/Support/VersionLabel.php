<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Switcher display names. Folders and URLs stay the list slug.
 */
final class VersionLabel
{
    /**
     * Label for one version folder: the config override, else the slug. That
     * a version is the latest is said by kind(), not by its name.
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

        return $version;
    }

    /**
     * Where a version stands: `latest`, `unreleased` or `older`. The list
     * runs newest first, so a version before the latest is not released yet.
     *
     * @return 'latest'|'unreleased'|'older'
     */
    public static function kind(string $version): string
    {
        $latest = config('vellum.versions.latest');

        if ($version === $latest) {
            return 'latest';
        }

        /** @var list<string> $list */
        $list = array_values(array_filter((array) config('vellum.versions.list', []), 'is_string'));
        $position = array_search($version, $list, true);
        $latestPosition = array_search($latest, $list, true);

        return $position !== false && $latestPosition !== false && $position < $latestPosition ? 'unreleased' : 'older';
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
