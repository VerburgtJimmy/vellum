<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;
use Vellum\Search\ScoutIndexer;
use Vellum\Search\SearchDriver;
use Vellum\Search\SearchIndexQuery;

/**
 * Live search: MiniSearch index or Scout hits, always filtered for the current user.
 */
final class SearchIndexController extends Controller
{
    public function __construct(
        private readonly SearchIndexQuery $query = new SearchIndexQuery,
    ) {}

    public function __invoke(Request $request, ?string $hash = null): Response
    {
        $repository = ContentRepository::fromConfig();
        $version = $request->query('version');
        $version = is_string($version) && $version !== '' ? $version : null;

        if (SearchDriver::isScout() && ($hash === null || $hash === '')) {
            SearchDriver::assertScoutInstalled();

            $q = $request->query('q');
            $q = is_string($q) ? trim($q) : '';
            $resolved = $this->resolveVersion($repository, $version);
            $documents = $q === '' ? [] : (new ScoutIndexer)->search($q, $resolved);

            return $this->jsonResponse($request, json_encode(
                ['driver' => 'scout', 'documents' => $documents],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        }

        $payload = $this->query->json($repository, $version, $hash);

        return $this->jsonResponse($request, $payload['json'], $payload['etag']);
    }

    private function resolveVersion(ContentRepository $repository, ?string $version): ?string
    {
        if (! $repository->versionsEnabled()) {
            return null;
        }

        if (is_string($version) && in_array($version, $repository->versions(), true)) {
            return $version;
        }

        return $repository->latestVersion();
    }

    private function jsonResponse(Request $request, string $json, ?string $etag = null): Response
    {
        $response = response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);

        if ($etag !== null && $etag !== '') {
            $response->setEtag($etag);
            $response->isNotModified($request);
        }

        return $response;
    }
}
