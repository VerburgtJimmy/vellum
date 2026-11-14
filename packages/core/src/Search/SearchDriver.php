<?php

declare(strict_types=1);

namespace Vellum\Search;

use RuntimeException;
use Vellum\Site;

/**
 * The search drivers' names, and the check that Scout is there when it is
 * the one configured. Which driver a site uses is on Vellum\Site\Search.
 */
final class SearchDriver
{
    /**
     * The in-browser driver. `minisearch` is the name it had while it used that
     * library, kept working so a published config keeps working.
     */
    public const BUILTIN = 'builtin';

    public const BUILTIN_ALIAS = 'minisearch';

    /**
     * @deprecated Use `$site->search->driver`. Kept for views published
     *             before 0.10, and removed in 1.0.
     */
    public static function name(): string
    {
        return app(Site::class)->search->driver;
    }

    /**
     * @deprecated Use `$site->search->isScout()`. Kept for views published
     *             before 0.10, and removed in 1.0.
     */
    public static function isScout(): bool
    {
        return app(Site::class)->search->isScout();
    }

    /**
     * The trait whose presence means laravel/scout is installed.
     *
     * @internal Tests point it at a missing trait to see the error.
     */
    public static string $scoutTrait = 'Laravel\\Scout\\Searchable';

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
