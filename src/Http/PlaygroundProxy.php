<?php

declare(strict_types=1);

namespace Vellum\Http;

/**
 * Decides what the playground's forwarding route will send, and where.
 *
 * A route that fetches an address a visitor names is a way into whatever the
 * server can reach and the visitor cannot. So the address has to be one the
 * site's own spec names, and the request carries nothing the reader did not
 * put in it.
 */
final class PlaygroundProxy
{
    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /** Largest request body forwarded, and largest response read, in bytes. */
    public const MAX_REQUEST = 1024 * 1024;

    public const MAX_RESPONSE = 2 * 1024 * 1024;

    /**
     * Headers that describe the reader's connection to this app, or that the
     * HTTP client must write itself. None of them is the reader's to forward.
     */
    private const WITHHELD = [
        'host', 'cookie', 'content-length', 'connection', 'transfer-encoding', 'keep-alive', 'upgrade', 'te', 'trailer',
        'origin', 'referer', 'forwarded', 'via', 'x-xsrf-token', 'x-csrf-token', 'x-real-ip', 'expect',
    ];

    /**
     * Whether a request may go to this address: under one of the servers the
     * spec names, on the same scheme, host and port.
     *
     * @param  list<string>  $servers
     */
    public static function allows(string $url, array $servers): bool
    {
        $target = self::parts($url);

        if ($target === null) {
            return false;
        }

        foreach ($servers as $server) {
            $base = self::parts($server);

            if ($base === null || $base['origin'] !== $target['origin']) {
                continue;
            }

            if ($base['path'] === '' || $target['path'] === $base['path'] || str_starts_with($target['path'], $base['path'].'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The headers to forward.
     *
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    public static function headers(array $headers): array
    {
        $out = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || ! is_string($value) || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) !== 1) {
                continue;
            }

            $lower = strtolower($name);

            if (in_array($lower, self::WITHHELD, true) || str_starts_with($lower, 'proxy-') || str_starts_with($lower, 'sec-') || str_starts_with($lower, 'x-forwarded-')) {
                continue;
            }

            // A line break in a value would start a header of its own.
            if (preg_match('/[\r\n\0]/', $value) === 1) {
                continue;
            }

            $out[$name] = $value;
        }

        return $out;
    }

    /**
     * Whether a response of this type is text a reader can be shown.
     */
    public static function textual(string $contentType): bool
    {
        return $contentType === ''
            || preg_match('#^text/|json|xml|javascript|x-www-form-urlencoded|csv|yaml#i', $contentType) === 1;
    }

    /**
     * An address reduced to what it is compared by, or null when it is not
     * one this route will go to: not http or https, carrying a username, or
     * with a path that climbs out of itself.
     *
     * @return array{origin: string, path: string}|null
     */
    private static function parts(string $url): ?array
    {
        if ($url === '' || preg_match('/[\s\\\\\x00-\x1f\x7f]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $path = $parts['path'] ?? '';
        $decoded = rawurldecode($path);

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        // A second round of encoding would hide a segment from the check above.
        if (str_contains($decoded, '%') && rawurldecode($decoded) !== $decoded) {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return [
            'origin' => $scheme.'://'.strtolower($parts['host']).':'.$port,
            'path' => rtrim($path, '/'),
        ];
    }
}
