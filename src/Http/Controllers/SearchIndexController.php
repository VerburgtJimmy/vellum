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
        $version = $repository->versionsEnabled() ? $repository->latestVersion() : null;

        // Ensure nav/search exist in local when the directory changed.
        $repository->navigation($version);

        $resolvedHash = $hash;

        if ($resolvedHash === null || $resolvedHash === '') {
            $resolvedHash = $repository->searchHash($version);
        }

        $json = $repository->store()->getSearchIndex($version, $resolvedHash);

        if ($json === null) {
            abort(404);
        }

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
