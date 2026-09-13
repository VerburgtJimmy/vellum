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
        $parsed = $repository->parseRequestSlug($slug);
        $version = $parsed['version'];
        $documentSlug = $parsed['slug'];

        if ($documentSlug === 'index') {
            $documentSlug = '';
        }

        $document = $repository->find($documentSlug, $version);

        if ($document === null || ! is_file($document->path) || ! $repository->allows($document)) {
            abort(404);
        }

        $contents = file_get_contents($document->path);

        return response($contents === false ? '' : $contents, 200)
            ->header('Content-Type', 'text/markdown; charset=UTF-8');
    }
}
