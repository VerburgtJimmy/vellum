<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Vellum\Content\ContentRepository;
use Vellum\Http\PlaygroundProxy;
use Vellum\OpenApi\Mount;
use Vellum\OpenApi\Playground;

/**
 * Sends a playground request on the reader's behalf, for an API on another
 * origin that does not let the browser read its answers.
 *
 * Off unless openapi.proxy is on. It answers only a reader who may open the
 * reference, and goes only to the servers the spec names. The response comes
 * back as data for the playground to show, never as a page of this app's.
 */
final class PlaygroundProxyController extends Controller
{
    private const TIMEOUT = 30;

    public function __invoke(Request $request): JsonResponse
    {
        if (! (bool) config('vellum.openapi.enabled') || ! Playground::enabled() || ! (bool) config('vellum.openapi.proxy', false)) {
            abort(404);
        }

        $repository = ContentRepository::fromConfig();
        $overview = $repository->find(Mount::prefix());

        if ($overview === null || ! $overview->generated() || ! $repository->allows($overview)) {
            abort(404);
        }

        // JSON only. Another site can make a visitor's browser post a form
        // here, but not JSON without this app's leave, so this holds even
        // where the docs' middleware has been set without CSRF protection.
        if (! $request->isJson()) {
            return $this->refuse('The request is not one the playground sends.', 415);
        }

        $method = strtoupper((string) $request->input('method'));
        $url = $request->input('url');
        $body = $request->input('body');
        $headers = $request->input('headers', []);

        if (! in_array($method, PlaygroundProxy::METHODS, true) || ! is_string($url) || ! is_array($headers) || ($body !== null && ! is_string($body))) {
            return $this->refuse('The request is not one the playground sends.', 422);
        }

        if ($body !== null && strlen($body) > PlaygroundProxy::MAX_REQUEST) {
            return $this->refuse('The body is too large to send through this site.', 413);
        }

        /** @var list<string> $servers */
        $servers = array_values(array_filter((array) ($overview->frontmatter['openapi_servers'] ?? []), is_string(...)));

        if (! PlaygroundProxy::allows($url, $servers)) {
            return $this->refuse('That address is not one of the servers this API reference names.', 422);
        }

        if (! class_exists(Client::class)) {
            return $this->refuse('This site cannot send requests: its HTTP client is not installed.', 501);
        }

        $forward = PlaygroundProxy::headers($headers);
        $started = microtime(true);

        try {
            $pending = Http::withHeaders($forward)
                ->timeout(self::TIMEOUT)
                ->connectTimeout(5)
                // A redirect is an answer to show, not an address to follow:
                // it could lead anywhere, the servers the spec names or not.
                ->withOptions(['allow_redirects' => false, 'http_errors' => false, 'stream' => true]);

            if ($body !== null && ! in_array($method, ['GET', 'HEAD'], true)) {
                $pending = $pending->withBody($body, $this->contentType($forward));
            }

            $response = $pending->send($method, $url);
        } catch (ConnectionException) {
            return $this->refuse('No response. The server may be down, or the address wrong.', 502);
        }

        $stream = $response->toPsrResponse()->getBody();
        $content = '';

        while (! $stream->eof() && strlen($content) <= PlaygroundProxy::MAX_RESPONSE) {
            $content .= $stream->read(65536);
        }

        $truncated = strlen($content) > PlaygroundProxy::MAX_RESPONSE;
        $content = $truncated ? substr($content, 0, PlaygroundProxy::MAX_RESPONSE) : $content;
        $type = $response->header('Content-Type');
        $textual = PlaygroundProxy::textual($type);
        $answered = [];

        foreach ($response->headers() as $name => $values) {
            // A cookie the API sets is for a browser talking to the API.
            if (strtolower((string) $name) !== 'set-cookie') {
                $answered[] = [(string) $name, implode(', ', $values)];
            }
        }

        return new JsonResponse([
            'status' => $response->status(),
            'statusText' => $response->reason(),
            'headers' => $answered,
            'type' => $type,
            'body' => $textual ? mb_convert_encoding($content, 'UTF-8', 'UTF-8') : null,
            'size' => strlen($content),
            'truncated' => $truncated,
            'elapsed' => (int) round((microtime(true) - $started) * 1000),
        ], 200, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function contentType(array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return $value;
            }
        }

        return 'application/octet-stream';
    }

    private function refuse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status, ['Cache-Control' => 'no-store']);
    }
}
