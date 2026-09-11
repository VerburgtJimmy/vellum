<?php

declare(strict_types=1);

namespace Vellum\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gzip compresses HTML responses when the client accepts it.
 */
final class CompressHtmlResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        $acceptsGzip = str_contains(strtolower($request->header('Accept-Encoding', '')), 'gzip');

        if (! $acceptsGzip) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/json')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $encoded = gzencode($content, 9);

        if ($encoded === false) {
            return $response;
        }

        $response->setContent($encoded);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding', false);

        return $response;
    }
}
