<?php

declare(strict_types=1);

namespace Vellum\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;

/**
 * The raw Markdown response for a page, shared by the raw route and by a
 * docs page request that asked for Markdown.
 */
final class RawMarkdown
{
    /**
     * Whether the Accept header ranks text/markdown above text/html.
     *
     * text/markdown has to be named outright with a quality above zero; a
     * wildcard never counts as asking for Markdown. text/html takes its
     * quality from its most specific match: text/html, then text/*, then the
     * any-type wildcard. On a tie, Markdown wins when HTML only matched a wildcard or when
     * Markdown was listed first. A browser's default Accept never names
     * text/markdown, so it keeps getting HTML.
     */
    public static function preferredBy(Request $request): bool
    {
        $items = [];

        foreach (AcceptHeader::fromString(strtolower((string) $request->headers->get('Accept', '')))->all() as $item) {
            $items[$item->getValue()] ??= $item;
        }

        $markdown = $items['text/markdown'] ?? null;

        if ($markdown === null || $markdown->getQuality() <= 0) {
            return false;
        }

        $html = $items['text/html'] ?? $items['text/*'] ?? $items['*/*'] ?? null;

        if ($html === null || $markdown->getQuality() > $html->getQuality()) {
            return true;
        }

        if ($markdown->getQuality() < $html->getQuality()) {
            return false;
        }

        return $html->getValue() !== 'text/html' || $markdown->getIndex() < $html->getIndex();
    }

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
