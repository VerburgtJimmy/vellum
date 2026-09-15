<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from the configured docs content directory.
 */
final class ContentFileController extends Controller
{
    /**
     * Asset extensions this route will serve. Markdown sources, meta files and
     * dotfiles are excluded by construction: page access is enforced on the docs
     * route, and this route has no document to check it against.
     *
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = [
        'apng', 'avif', 'bmp', 'gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'webp',
        'mp3', 'mp4', 'ogg', 'wav', 'webm',
        'eot', 'otf', 'ttf', 'woff', 'woff2',
        'csv', 'pdf', 'txt', 'vtt', 'zip',
    ];

    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $contentPath = realpath((string) config('vellum.path'));

        if ($contentPath === false || ! is_dir($contentPath)) {
            abort(404);
        }

        $relative = str_replace('\\', '/', $path);
        $relative = ltrim($relative, '/');

        if ($relative === '' || str_contains($relative, '..')) {
            abort(404);
        }

        foreach (explode('/', $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                abort(404);
            }
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            abort(404);
        }

        $absolute = realpath($contentPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($absolute === false || ! is_file($absolute) || ! str_starts_with($absolute, $contentPath.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $mime = mime_content_type($absolute) ?: 'application/octet-stream';

        // Not immutable: these URLs carry no content hash, so a replaced asset has
        // to be able to win before the cache entry would otherwise expire.
        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
        ]);
    }
}
