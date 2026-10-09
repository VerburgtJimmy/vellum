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

    /** Headers every request may carry, whatever the spec declares. */
    private const ALWAYS = ['accept', 'content-type', 'authorization'];

    /**
     * Headers that are never forwarded, even if a spec declares one: they
     * describe a connection, which here is this app's and not the reader's,
     * or say who a request is from, which the API would take from this app's
     * address on trust.
     */
    private const WITHHELD = [
        'host', 'cookie', 'content-length', 'connection', 'transfer-encoding', 'keep-alive', 'upgrade', 'te', 'trailer',
        'origin', 'referer', 'forwarded', 'via', 'expect', 'x-xsrf-token', 'x-csrf-token',
        'x-real-ip', 'client-ip', 'true-client-ip', 'x-client-ip', 'x-cluster-client-ip', 'x-host', 'x-rewrite-url',
        'x-http-method-override', 'x-http-method', 'x-method-override',
    ];

    private const WITHHELD_PREFIXES = ['proxy-', 'sec-', 'x-forwarded-', 'x-original-', 'cf-', 'fastly-', 'x-envoy-', 'x-amzn-'];

    /**
     * The address a request may go to, or null when it may not go there: it
     * has to be under one of the servers the spec names, on the same scheme,
     * host and port.
     *
     * What comes back is the address written out again from the parts that
     * were checked, and that is what must be requested. The string a reader
     * sent may be read differently by the HTTP client than it was here.
     *
     * @param  list<string>  $servers
     */
    public static function target(string $url, array $servers): ?string
    {
        $target = self::parts($url);

        if ($target === null) {
            return null;
        }

        foreach ($servers as $server) {
            $base = self::parts($server);

            if ($base === null || $base['origin'] !== $target['origin']) {
                continue;
            }

            if ($base['path'] === '' || $target['path'] === $base['path'] || str_starts_with($target['path'], $base['path'].'/')) {
                return $target['address'].$target['rest'];
            }
        }

        return null;
    }

    /**
     * The headers to forward: those the spec declares for some operation,
     * and the three any request may carry. A reader's browser can send the
     * API any header it likes. This app cannot, because the API may trust
     * what arrives from this app's address.
     *
     * @param  array<array-key, mixed>  $headers
     * @param  list<string>  $declared  Header names from the spec, in lower case.
     * @return array<string, string>
     */
    public static function headers(array $headers, array $declared = []): array
    {
        $out = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || ! is_string($value) || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) !== 1) {
                continue;
            }

            $lower = strtolower($name);

            if (! in_array($lower, self::ALWAYS, true) && ! in_array($lower, $declared, true)) {
                continue;
            }

            if (in_array($lower, self::WITHHELD, true)) {
                continue;
            }

            foreach (self::WITHHELD_PREFIXES as $prefix) {
                if (str_starts_with($lower, $prefix)) {
                    continue 2;
                }
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
     * @return array{origin: string, address: string, path: string, rest: string}|null
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
        $host = strtolower($parts['host']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        // A name, an IPv4 address or a bracketed IPv6 one, in plain ASCII.
        // Anything else is a character some client may read as a separator.
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:.]+\])$/', $host) !== 1) {
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

        if (preg_match('#^(?:/[A-Za-z0-9._~!$&\'()*+,;=:@%-]*)*$#', $path) !== 1) {
            return null;
        }

        $query = $parts['query'] ?? null;

        if ($query !== null && preg_match('#^[A-Za-z0-9._~!$&\'()*+,;=:@%/?\[\]-]*$#', $query) !== 1) {
            return null;
        }

        return [
            // With the port always written, so :443 and no port compare equal.
            'origin' => $scheme.'://'.$host.':'.$port,
            // As it is requested: the port only where the address gave one.
            'address' => $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : ''),
            'path' => rtrim($path, '/'),
            // The fragment is left behind: it is never sent to a server.
            'rest' => $path.($query === null ? '' : '?'.$query),
        ];
    }
}
