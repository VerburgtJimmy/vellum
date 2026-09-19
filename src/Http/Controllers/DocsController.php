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
use Vellum\Http\RawMarkdown;

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

        if ($repository->versionsEnabled() && $repository->shouldRedirectToUnprefixed($slug)) {
            $target = $repository->unprefixedPath($slug);

            if ($target === '') {
                return redirect()->route('vellum.docs.index', status: 301);
            }

            return redirect()->route('vellum.docs.show', ['slug' => $target], 301);
        }

        $parsed = $repository->parseRequestSlug($slug);
        $version = $parsed['version'];
        $documentSlug = $parsed['slug'];

        $document = $repository->find($documentSlug, $version);
        $negotiates = (bool) config('vellum.agents.content_negotiation', true);

        if ($negotiates && RawMarkdown::preferredBy(request())) {
            $response = RawMarkdown::visible($repository, $document)
                ? RawMarkdown::response($repository, $document)
                : response('Not found.', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        } elseif ($document === null || ! $repository->allows($document)) {
            $response = $this->notFound($repository, $version);
        } else {
            $response = $this->render($repository, $document);
        }

        // One URL, two bodies depending on Accept. Without this a cache could
        // hand the HTML to an agent, or the Markdown to a browser.
        if ($negotiates) {
            $response->setVary('Accept', false);
        }

        return $response;
    }

    private function notFound(ContentRepository $repository, ?string $version): Response
    {
        $switcher = $repository->versionSwitcherData('', $version);

        $html = $this->view->file(
            dirname(__DIR__, 3).'/resources/views/pages/404.blade.php',
            [
                'name' => config('vellum.name'),
                'description' => 'Page not found',
                'pageTitle' => DocsView::pageTitle('Page not found'),
                'noindex' => true,
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

        // The same pointer as the <link rel="alternate"> in the head, for a
        // client that reads headers and never parses the HTML.
        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Link', '<'.DocsView::rawUrl($document).'>; rel="alternate"; type="text/markdown"');
    }
}
