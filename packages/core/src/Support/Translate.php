<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * A line from the language files, in the app's locale, as a string.
 */
final class Translate
{
    /**
     * @param  array<string, string|int|float>  $replace
     */
    public static function line(string $key, array $replace = []): string
    {
        $line = app('translator')->get($key, $replace);

        return is_string($line) ? $line : $key;
    }
}
