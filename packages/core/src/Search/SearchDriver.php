<?php

declare(strict_types=1);

namespace Vellum\Search;

use RuntimeException;

/**
 * Resolves the configured search driver.
 */
final class SearchDriver
{
    /**
     * The in-browser driver. `minisearch` is the name it had while it used that
     * library, kept working so a published config keeps working.
     */
    public const BUILTIN = 'builtin';

    public const BUILTIN_ALIAS = 'minisearch';

    public static function name(): string
    {
        $driver = config('vellum.search.driver', self::BUILTIN);
        $driver = is_string($driver) && $driver !== '' ? $driver : self::BUILTIN;

        return $driver === self::BUILTIN_ALIAS ? self::BUILTIN : $driver;
    }

    /**
     * The trait whose presence means laravel/scout is installed.
     *
     * @internal Tests point it at a missing trait to see the error.
     */
    public static string $scoutTrait = 'Laravel\\Scout\\Searchable';

    public static function isScout(): bool
    {
        return self::name() === 'scout';
    }

    public static function assertScoutInstalled(): void
    {
        // Searchable is a trait, which class_exists() never reports.
        if (! trait_exists(self::$scoutTrait)) {
            throw new RuntimeException(
                'vellum.search.driver is scout but laravel/scout is not installed. Run composer require laravel/scout.',
            );
        }
    }
}
