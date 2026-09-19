<?php

declare(strict_types=1);

namespace Vellum\Http;

use Illuminate\Http\Response;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;

/**
 * The raw Markdown response for a page, shared by the raw route and by a
 * docs page request that asked for Markdown.
 */
final class RawMarkdown
{
    /**
     * Whether the source may be served to the current reader. Gating is the
     * same as for the HTML page.
     *
     * @phpstan-assert-if-true Document $document
     */
    public static function visible(ContentRepository $repository, ?Document $document): bool
    {
        return $document !== null && is_file($document->path) && $repository->allows($document);
    }

    /**
     * The file exactly as written. The version goes in a header rather than
     * the body, so Copy Markdown and this response stay byte for byte the same.
     */
    public static function response(ContentRepository $repository, Document $document): Response
    {
        $response = response(DocsView::source($document), 200)
            ->header('Content-Type', 'text/markdown; charset=UTF-8');

        if ($repository->versionsEnabled() && is_string($document->version) && $document->version !== '') {
            $response->header('X-Vellum-Docs-Version', $document->version);
        }

        return $response;
    }
}
