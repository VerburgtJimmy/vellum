<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Support\Sitemap;

/**
 * Serves the docs sitemap at {prefix}/sitemap.xml.
 *
 * It sits under the docs prefix rather than at the site root, which the
 * package does not own. A sitemap may list any URL at or below its own path,
 * so one at /docs/sitemap.xml covers every docs page.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $entries = Sitemap::entries(ContentRepository::fromConfig());

        if ($entries === []) {
            abort(404);
        }

        return response(Sitemap::render($entries), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
