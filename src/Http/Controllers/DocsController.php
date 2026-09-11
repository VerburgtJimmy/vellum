<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\HeadingExtractor;

/**
 * Serves compiled documentation pages.
 */
final class DocsController extends Controller
{
    public function __construct(
        private readonly ViewFactory $view,
        private readonly HeadingExtractor $headingExtractor = new HeadingExtractor,
    ) {}

    public function index(): Response|RedirectResponse
    {
        return $this->show('');
    }

    public function show(string $slug = ''): Response|RedirectResponse
    {
        $repository = ContentRepository::fromConfig();
        $slug = trim($slug, '/');

        if ($repository->versionsEnabled() && $repository->shouldRedirectToLatest($slug)) {
            $latest = $repository->latestVersion();

            if ($latest !== null) {
                $target = trim($latest.'/'.$slug, '/');

                return redirect()->route('vellum.docs.show', ['slug' => $target], 302);
            }
        }

        $version = null;
        $documentSlug = $slug;

        if ($repository->versionsEnabled()) {
            $parts = $slug === '' ? [] : explode('/', $slug, 2);
            $first = $parts[0] ?? '';

            if (in_array($first, $repository->versions(), true)) {
                $version = $first;
                $documentSlug = $parts[1] ?? '';
            } else {
                $version = $repository->latestVersion();
            }
        }

        $document = $repository->find($documentSlug, $version);

        if ($document === null) {
            abort(404);
        }

        return $this->render($repository, $document);
    }

    private function render(ContentRepository $repository, Document $document): Response
    {
        $navigation = $repository->navigation($document->version);
        $adjacent = $repository->adjacent($document->slug, $document->version);
        $breadcrumbs = $repository->breadcrumbs($document);
        $toc = $this->headingExtractor->nest($document->headings);

        $html = $this->view->file(
            dirname(__DIR__, 3).'/resources/views/pages/doc.blade.php',
            [
                'document' => $document,
                'name' => config('vellum.name'),
                'description' => $document->description,
                'navigation' => $navigation,
                'previous' => $adjacent['previous'],
                'next' => $adjacent['next'],
                'breadcrumbs' => $breadcrumbs,
                'toc' => $toc,
                'searchHash' => $repository->searchHash($document->version),
            ],
        )->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
