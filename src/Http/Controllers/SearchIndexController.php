<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Search\ScoutIndexer;
use Vellum\Search\SearchDriver;

/**
 * Scout search, filtered for the current user. The built-in driver needs no
 * endpoint of its own: it searches the answer index in the browser.
 */
final class SearchIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! SearchDriver::isScout()) {
            abort(404);
        }

        SearchDriver::assertScoutInstalled();

        $repository = ContentRepository::fromConfig();
        $version = $request->query('version');
        $query = $request->query('q');
        $query = is_string($query) ? trim($query) : '';

        $documents = $query === '' ? [] : (new ScoutIndexer)->search($query, $this->resolveVersion(
            $repository,
            is_string($version) && $version !== '' ? $version : null,
        ));

        return response(
            (string) json_encode(['driver' => 'scout', 'documents' => $documents], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        );
    }

    private function resolveVersion(ContentRepository $repository, ?string $version): ?string
    {
        if (! $repository->versionsEnabled()) {
            return null;
        }

        return $version !== null && in_array($version, $repository->versions(), true)
            ? $version
            : $repository->latestVersion();
    }
}
