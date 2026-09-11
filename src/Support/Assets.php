<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Resolves package asset URLs with content-hash cache busting.
 */
final class Assets
{
    /**
     * Absolute path to the compiled CSS file.
     */
    public static function cssPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum.css';
    }

    /**
     * Absolute path to the compiled JS file.
     */
    public static function jsPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum.js';
    }

    /**
     * Public URL for the compiled CSS, with a short content hash query.
     */
    public static function cssUrl(): string
    {
        return route('vellum.assets.css', ['v' => self::hash(self::cssPath())]);
    }

    /**
     * Public URL for the compiled JS, with a short content hash query.
     */
    public static function jsUrl(): string
    {
        return route('vellum.assets.js', ['v' => self::hash(self::jsPath())]);
    }

    private static function hash(string $path): string
    {
        if (! is_file($path)) {
            return '0';
        }

        $hash = md5_file($path);

        return is_string($hash) ? substr($hash, 0, 8) : '0';
    }
}
