<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Support\LlmsTxt;

/**
 * Serves {prefix}/llms.txt and {prefix}/llms-full.txt.
 *
 * Both sit under the docs prefix for the same reason the sitemap does: the
 * site root belongs to the host app, not the package.
 */
final class LlmsTxtController extends Controller
{
    public function index(): Response
    {
        return $this->respond(static fn (ContentRepository $repository): string => LlmsTxt::index($repository));
    }

    public function full(): Response
    {
        return $this->respond(static fn (ContentRepository $repository): string => LlmsTxt::full($repository));
    }

    /**
     * @param  callable(ContentRepository): string  $build
     */
    private function respond(callable $build): Response
    {
        if (! LlmsTxt::enabled()) {
            abort(404);
        }

        return response($build(ContentRepository::fromConfig()), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
