<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Changelog\Changelog;
use Vellum\Changelog\ChangelogFeed;
use Vellum\Content\ContentRepository;
use Vellum\Http\DocsView;
use Vellum\Http\LinkHeader;
use Vellum\Http\RawMarkdown;

/**
 * Serves the changelog HTML page and Atom feed.
 */
final class ChangelogController extends Controller
{
    public function __construct(
        private readonly ViewFactory $view,
        private readonly ChangelogFeed $feed = new ChangelogFeed,
    ) {}

    public function page(): Response|RedirectResponse
    {
        $changelog = Changelog::load();

        if ($changelog === null) {
            return app(DocsController::class)->show('changelog');
        }

        $repository = ContentRepository::fromConfig();
        $negotiates = (bool) config('vellum.agents.content_negotiation', true);

        if ($negotiates && RawMarkdown::preferredBy(request())) {
            $response = RawMarkdown::changelog($repository, $changelog);
        } else {
            $data = DocsView::changelog($repository, $changelog);
            $html = $this->view->file(dirname(__DIR__, 3).'/resources/views/pages/changelog.blade.php', $data)->render();

            $response = response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
            LinkHeader::add($response, route('vellum.raw', ['slug' => 'changelog']), 'alternate', 'text/markdown');
        }

        // The same reason as docs pages: one URL, two bodies.
        if ($negotiates) {
            $response->setVary('Accept', false);
        }

        return $response;
    }

    public function feed(): Response
    {
        $changelog = Changelog::load();

        if ($changelog === null) {
            abort(404);
        }

        return response($this->feed->render($changelog), 200)
            ->header('Content-Type', 'application/atom+xml; charset=UTF-8');
    }
}
