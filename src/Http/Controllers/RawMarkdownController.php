<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;

/**
 * Serves the raw Markdown source of a documentation page.
 */
final class RawMarkdownController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $repository = ContentRepository::fromConfig();
        $slug = trim($slug, '/');
        $version = null;
        $documentSlug = $slug;

        if ($repository->versionsEnabled()) {
            $parts = $slug === '' ? [] : explode('/', $slug, 2);
            $first = $parts[0] ?? '';

            if (in_array($first, $repository->versions(), true)) {
                $version = $first;
                $documentSlug = $parts[1] ?? '';
            }
        }

        if ($documentSlug === 'index') {
            $documentSlug = '';
        }

        $document = $repository->find($documentSlug, $version);

        if ($document === null || ! is_file($document->path)) {
            abort(404);
        }

        $contents = file_get_contents($document->path);

        return response($contents === false ? '' : $contents, 200)
            ->header('Content-Type', 'text/markdown; charset=UTF-8');
    }
}
