<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Answers\AnswersQuery;
use Vellum\Answers\Ranker;
use Vellum\Content\ContentRepository;
use Vellum\Semantic\SemanticQuery;

/**
 * The answer index and the semantic vectors search loads in the browser, always
 * filtered for the reader asking. Neither is a public file: a gated page's text
 * and even its words are only in the copy a reader with that access gets.
 *
 * The answer endpoint runs the same search on the server, for a client that
 * cannot run the browser's copy: an agent, a chat bot, a shell script.
 */
final class AnswersController extends Controller
{
    /**
     * Results the answer endpoint returns, beyond the card.
     */
    private const LIMIT = 5;

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

    /**
     * One question, answered from the docs the caller may see. The same ranker
     * the browser runs, so an agent and a reader get the same answer, and the
     * same access rules, so neither is told about a page they cannot open.
     */
    public function answer(Request $request): Response
    {
        if (! (bool) config('vellum.answers.enabled', true) || ! (bool) config('vellum.agents.answer', true)) {
            abort(404);
        }

        $query = $request->query('q');
        $query = is_string($query) ? trim($query) : '';
        $repository = ContentRepository::fromConfig();
        $version = self::version($request);
        $index = $this->query->index($repository, $version);

        if ($index === null) {
            abort(404);
        }

        if ($query === '') {
            return $this->json($request, ['query' => '', 'answer' => null, 'results' => []], 400);
        }

        $visible = $index->forAccess($this->query->allowed($index));
        $bytes = (bool) config('vellum.answers.semantic', true)
            ? $this->query->semantic($repository, $version)
            : null;

        $result = (new Ranker($visible, $bytes === null ? null : new SemanticQuery($bytes['bytes'])))
            ->search($query, self::LIMIT);

        return $this->json($request, [
            'query' => $query,
            'answer' => $result['card'] === null ? null : [
                ...self::hit($result['card']),
                'answer' => $result['card']['record']['answer'],
                'confidence' => round($result['confidence'], 3),
            ],
            'results' => array_map(self::hit(...), $result['results']),
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
    private function json(Request $request, array $payload, int $status = 200): Response
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
