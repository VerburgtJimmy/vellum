<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Http\LlmsTxt;

/**
 * Serves {prefix}/llms.txt and {prefix}/llms-full.txt, and the same files at
 * the site root when the host app has not taken those paths.
 */
final class LlmsTxtController extends Controller
{
    public function index(ContentRepository $repository): Response
    {
        return $this->respond(LlmsTxt::index(...), $repository);
    }

    public function full(ContentRepository $repository): Response
    {
        return $this->respond(LlmsTxt::full(...), $repository);
    }

    public function rootIndex(ContentRepository $repository): Response
    {
        $this->abortUnlessRoot();

        return $this->index($repository);
    }

    public function rootFull(ContentRepository $repository): Response
    {
        $this->abortUnlessRoot();

        return $this->full($repository);
    }

    /**
     * The root routes are registered at boot, so the key is checked again
     * here for a config changed after that.
     */
    private function abortUnlessRoot(): void
    {
        if (! (bool) config('vellum.agents.llms_txt_root', true)) {
            abort(404);
        }
    }

    /**
     * @param  callable(ContentRepository): string  $build
     */
    private function respond(callable $build, ContentRepository $repository): Response
    {
        if (! LlmsTxt::enabled()) {
            abort(404);
        }

        return response($build($repository), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
