<?php

declare(strict_types=1);

namespace Vellum\Search;

use RuntimeException;

/**
 * Resolves the configured search driver.
 */
final class SearchDriver
{
    public static function name(): string
    {
        $driver = config('vellum.search.driver', 'minisearch');

        return is_string($driver) && $driver !== '' ? $driver : 'minisearch';
    }

    public static function isScout(): bool
    {
        return self::name() === 'scout';
    }

    public static function assertScoutInstalled(): void
    {
        if (! class_exists('Laravel\\Scout\\Searchable')) {
            throw new RuntimeException(
                'vellum.search.driver is scout but laravel/scout is not installed. Run composer require laravel/scout.',
            );
        }
    }
}
