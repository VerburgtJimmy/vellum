<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Answers\AnswersQuery;
use Vellum\Answers\Ranker;
use Vellum\Content\ContentRepository;

/**
 * The search index the browser loads, always filtered for the reader asking,
 * so a gated page's text is only in the copy a reader with that access gets.
 *
 * The search endpoint runs the same ranking on the server, for a client that
 * cannot run the browser's copy, such as an agent or a script.
 */
final class AnswersController extends Controller
{
    /**
     * Results the search endpoint returns.
     */
    private const LIMIT = 5;

    public function __construct(private readonly AnswersQuery $query = new AnswersQuery) {}

    public function index(Request $request): Response
    {
        if (! (bool) config('vellum.search.enabled', true)) {
            abort(404);
        }

        $payload = $this->query->json(ContentRepository::fromConfig(), self::version($request));

        if ($payload === null) {
            abort(404);
        }

        return $this->respond($request, $payload['json'], 'application/json; charset=UTF-8', $payload['etag']);
    }

    /**
     * One query, searched in the docs the caller may see, with the same
     * ranking the browser runs and the same access rules, so an agent is never
     * told about a page it cannot open.
     */
    public function search(Request $request): Response
    {
        if (! (bool) config('vellum.agents.search', true)) {
            abort(404);
        }

        $query = $request->query('q');
        $query = is_string($query) ? trim($query) : '';
        $index = $this->query->index(ContentRepository::fromConfig(), self::version($request));

        if ($index === null) {
            abort(404);
        }

        if ($query === '') {
            return $this->json(['query' => '', 'results' => []], 400);
        }

        $visible = $index->forAccess($this->query->allowed($index));

        return $this->json([
            'query' => $query,
            'results' => array_map(self::hit(...), (new Ranker($visible))->search($query, self::LIMIT)),
        ]);
    }

    /**
     * @param  array{record: array<string, mixed>, score: float}  $hit
     * @return array<string, mixed>
     */
    private static function hit(array $hit): array
    {
        $record = $hit['record'];

        return [
            'url' => $record['url'],
            'title' => $record['title'],
            'heading' => $record['heading'],
            'passage' => $record['passage'],
            'updated' => $record['updated'],
            'score' => round($hit['score'], 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload, int $status = 200): Response
    {
        return response(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $status,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        );
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
