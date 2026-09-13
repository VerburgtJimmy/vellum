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

        $html = $this->view->file(
            dirname(__DIR__, 3).'/resources/views/pages/changelog.blade.php',
            DocsView::changelog(ContentRepository::fromConfig(), $changelog),
        )->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
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
