<?php

declare(strict_types=1);

namespace Vellum\Support;

use TalesFromADev\TailwindMerge\TailwindMerge;

/**
 * Merges Tailwind class lists, resolving conflicts.
 */
final class Cn
{
    private static ?TailwindMerge $merge = null;

    /**
     * Merge one or more class lists into a single conflict-free string.
     */
    public static function merge(?string ...$classes): string
    {
        $filtered = array_values(array_filter(
            $classes,
            static fn (?string $class): bool => $class !== null && $class !== '',
        ));

        if ($filtered === []) {
            return '';
        }

        return self::instance()->merge(...$filtered);
    }

    private static function instance(): TailwindMerge
    {
        return self::$merge ??= new TailwindMerge;
    }
}
