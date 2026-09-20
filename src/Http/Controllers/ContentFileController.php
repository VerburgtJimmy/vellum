<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Vellum\Content\ContentFiles;

/**
 * Serves files from the configured docs content directory.
 */
final class ContentFileController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $contentPath = realpath((string) config('vellum.path'));

        if ($contentPath === false || ! is_dir($contentPath)) {
            abort(404);
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');

        if (! ContentFiles::isPublic($relative)) {
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
