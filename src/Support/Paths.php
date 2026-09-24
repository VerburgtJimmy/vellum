<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * Config paths are read relative to the app, as Laravel's own are. A relative
 * path would otherwise resolve against whatever directory PHP runs in, which
 * is the project root for Artisan but public/ for a web request.
 */
final class Paths
{
    private const KEYS = ['vellum.path', 'vellum.cache.path', 'vellum.changelog.path', 'vellum.export.out', 'vellum.answers.model_path'];

    public static function resolveConfig(): void
    {
        foreach (self::KEYS as $key) {
            $value = config($key);

            if (is_string($value) && $value !== '') {
                config()->set($key, self::absolute($value));
            }
        }

        // The changelog may also be configured as a bare path.
        if (is_string($changelog = config('vellum.changelog')) && $changelog !== '') {
            config()->set('vellum.changelog', self::absolute($changelog));
        }
    }

    public static function absolute(string $path): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
