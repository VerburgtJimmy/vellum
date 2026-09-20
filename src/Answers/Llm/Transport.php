<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use RuntimeException;

/**
 * One JSON POST over PHP's own HTTP stream wrapper, so the package needs no
 * HTTP client or SDK. Tests swap it for a fake.
 */
class Transport
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function post(string $url, array $headers, array $body): array
    {
        $lines = ['Content-Type: application/json'];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $lines),
            'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'timeout' => 120,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException("No response from {$url}");
        }

        $status = 0;

        foreach (http_get_last_response_headers() ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        $decoded = json_decode($response, true);

        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
    }
}
