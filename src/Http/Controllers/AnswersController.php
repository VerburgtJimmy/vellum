<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Answers\AnswersQuery;
use Vellum\Content\ContentRepository;

/**
 * The answer index and the semantic vectors search loads in the browser, always
 * filtered for the reader asking. Neither is a public file: a gated page's text
 * and even its words are only in the copy a reader with that access gets.
 */
final class AnswersController extends Controller
{
    public function __construct(private readonly AnswersQuery $query = new AnswersQuery) {}

    public function index(Request $request): Response
    {
        if (! (bool) config('vellum.answers.enabled', true)) {
            abort(404);
        }

        $payload = $this->query->json(ContentRepository::fromConfig(), self::version($request));

        if ($payload === null) {
            abort(404);
        }

        return $this->respond($request, $payload['json'], 'application/json; charset=UTF-8', $payload['etag']);
    }

    public function semantic(Request $request): Response
    {
        if (! (bool) config('vellum.answers.enabled', true) || ! (bool) config('vellum.answers.semantic', true)) {
            abort(404);
        }

        $payload = $this->query->semantic(ContentRepository::fromConfig(), self::version($request));

        if ($payload === null) {
            abort(404);
        }

        return $this->respond($request, $payload['bytes'], 'application/octet-stream', $payload['etag']);
    }

    private static function version(Request $request): ?string
    {
        $version = $request->query('version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    private function respond(Request $request, string $body, string $type, string $etag): Response
    {
        $response = response($body, 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);

        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }
}
