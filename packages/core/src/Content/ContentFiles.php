<?php

declare(strict_types=1);

namespace Vellum\Content;

/**
 * Which files in the docs directory are public assets. The asset route and the
 * static export share this one rule, so an export never ships a file the route
 * would refuse: Markdown sources, meta files, anything under a dot-directory
 * (such as .vellum), dotfiles, and any extension not listed here.
 */
final class ContentFiles
{
    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'apng', 'avif', 'bmp', 'gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'webp',
        'mp3', 'mp4', 'ogg', 'wav', 'webm',
        'eot', 'otf', 'ttf', 'woff', 'woff2',
        'csv', 'pdf', 'txt', 'vtt', 'zip',
    ];

    /**
     * @param  string  $relative  path under the docs directory, forward slashes
     */
    public static function isPublic(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..')) {
            return false;
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return false;
            }
        }

        return in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true);
    }
}
