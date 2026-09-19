<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Http\RawMarkdown;

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

        if (! RawMarkdown::visible($repository, $document)) {
            abort(404);
        }

        return RawMarkdown::response($repository, $document);
    }
}
