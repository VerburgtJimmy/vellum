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
use Vellum\Http\DocsView;

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
            return $this->notFound($repository, $version);
        }

        return $this->render($repository, $document);
    }

    private function notFound(ContentRepository $repository, ?string $version): Response
    {
        $switcher = $repository->versionSwitcherData('', $version);

        $html = $this->view->file(
            dirname(__DIR__, 3).'/resources/views/pages/404.blade.php',
            [
                'name' => config('vellum.name'),
                'description' => 'Page not found',
                'navigation' => $repository->navigation($version),
                'searchHash' => $repository->searchHash($version),
                'versions' => $switcher['versions'],
                'currentVersion' => $switcher['currentVersion'],
                'versionHrefs' => $switcher['versionHrefs'],
                'searchPlacement' => config('vellum.layout.search', 'sidebar') === 'header' ? 'header' : 'sidebar',
            ],
        )->render();

        return response($html, 404)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function render(ContentRepository $repository, Document $document): Response
    {
        $html = $this->view->file(
            dirname(__DIR__, 3).'/resources/views/pages/doc.blade.php',
            DocsView::document($repository, $document, $this->headingExtractor),
        )->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
