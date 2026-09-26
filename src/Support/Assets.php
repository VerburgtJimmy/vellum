<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Resolves package asset URLs with content-hash cache busting.
 */
final class Assets
{
    public static function cssPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum.css';
    }

    public static function jsPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum.js';
    }

    public static function searchJsPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum-search.js';
    }

    public static function anchorJsPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum-anchor.js';
    }

    public static function focusJsPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/vellum-focus.js';
    }

    public static function cssUrl(): string
    {
        return route('vellum.assets.css', ['v' => self::hash(self::cssPath())]);
    }

    public static function jsUrl(): string
    {
        return route('vellum.assets.js', ['v' => self::hash(self::jsPath())]);
    }

    public static function searchJsUrl(): string
    {
        return route('vellum.assets.search', ['v' => self::hash(self::searchJsPath())]);
    }

    public static function anchorJsUrl(): string
    {
        return route('vellum.assets.anchor', ['v' => self::hash(self::anchorJsPath())]);
    }

    public static function focusJsUrl(): string
    {
        return route('vellum.assets.focus', ['v' => self::hash(self::focusJsPath())]);
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
