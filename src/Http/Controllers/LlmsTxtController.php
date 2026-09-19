<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Support\LlmsTxt;

/**
 * Serves {prefix}/llms.txt and {prefix}/llms-full.txt, and the same files at
 * the site root when the host app has not taken those paths.
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

    public function rootIndex(): Response
    {
        $this->abortUnlessRoot();

        return $this->index();
    }

    public function rootFull(): Response
    {
        $this->abortUnlessRoot();

        return $this->full();
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
