<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

/**
 * JSON POSTs with no HTTP client or SDK. Several at once through curl_multi
 * when ext-curl is there, one after another over PHP's own HTTP streams when
 * it is not. A failed request is a response with status 0, not an exception:
 * the caller decides what to retry.
 *
 * @phpstan-type Request array{url: non-empty-string, headers: array<string, string>, body: array<string, mixed>}
 * @phpstan-type Response array{status: int, headers: array<string, string>, body: array<string, mixed>}
 */
class Transport
{
    /**
     * @param  non-empty-string  $url
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return Response
     */
    public function post(string $url, array $headers, array $body): array
    {
        return $this->postMany([['url' => $url, 'headers' => $headers, 'body' => $body]])[0];
    }

    /**
     * @param  list<Request>  $requests
     * @return list<Response>
     */
    public function postMany(array $requests): array
    {
        return extension_loaded('curl') ? $this->viaCurl($requests) : array_map($this->viaStream(...), $requests);
    }

    /**
     * @param  list<Request>  $requests
     * @return list<Response>
     */
    private function viaCurl(array $requests): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($requests as $index => $request) {
            $headers = [];

            foreach ($request['headers'] as $name => $value) {
                $headers[] = "{$name}: {$value}";
            }

            $handle = curl_init();
            curl_setopt_array($handle, [
                CURLOPT_URL => $request['url'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($request['body'], JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', ...$headers],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => 'vellum',
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$index] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $responses = [];

        foreach ($handles as $index => $handle) {
            $raw = (string) curl_multi_getcontent($handle);
            $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $responses[$index] = [
                'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'headers' => self::parseHeaders(substr($raw, 0, $headerSize)),
                'body' => self::decode(substr($raw, $headerSize)),
            ];
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }

        curl_multi_close($multi);
        ksort($responses);

        return array_values($responses);
    }

    /**
     * @param  Request  $request
     * @return Response
     */
    private function viaStream(array $request): array
    {
        $lines = ['Content-Type: application/json', 'User-Agent: vellum'];

        foreach ($request['headers'] as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $lines),
            'content' => json_encode($request['body'], JSON_THROW_ON_ERROR),
            'timeout' => 120,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($request['url'], false, $context);
        $headers = http_get_last_response_headers() ?? [];
        $status = 0;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return [
            'status' => $response === false ? 0 : $status,
            'headers' => self::parseHeaders(implode("\r\n", $headers)),
            'body' => self::decode($response === false ? '' : $response),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
