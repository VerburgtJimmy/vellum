<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Content\ContentRepository;

/**
 * Serves the compiled MiniSearch document index.
 */
final class SearchIndexController extends Controller
{
    public function __invoke(?string $hash = null): Response
    {
        $repository = ContentRepository::fromConfig();
        $store = $repository->store();

        if ($hash !== null && $hash !== '') {
            $json = $this->findHashedIndex($repository, $hash);

            if ($json === null) {
                abort(404);
            }

            return $this->jsonResponse($json);
        }

        $version = $repository->versionsEnabled() ? $repository->latestVersion() : null;

        // Ensure nav/search exist in local when the directory changed.
        $repository->navigation($version);

        $resolvedHash = $repository->searchHash($version);
        $json = $store->getSearchIndex($version, $resolvedHash);

        if ($json === null) {
            abort(404);
        }

        return $this->jsonResponse($json);
    }

    private function findHashedIndex(ContentRepository $repository, string $hash): ?string
    {
        $store = $repository->store();
        $candidates = $repository->versionsEnabled()
            ? $repository->versions()
            : [null];

        if ($repository->versionsEnabled()) {
            $candidates[] = null;
        }

        foreach ($candidates as $version) {
            $json = $store->getSearchIndex($version, $hash);

            if ($json !== null) {
                return $json;
            }
        }

        return null;
    }

    private function jsonResponse(string $json): Response
    {
        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
