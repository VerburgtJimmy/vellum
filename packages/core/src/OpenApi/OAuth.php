<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * The OAuth 2 flows a playground can run for a reader, from a scheme.
 *
 * Two of the spec's flows are offered. The authorization code flow, with
 * PKCE, is the one a browser is meant to use: the reader signs in with the
 * provider and comes back with a code no one else can turn into a token.
 * Client credentials is for an API's machine clients, and the reader types
 * the client's id and secret. Implicit and password are left out: both are
 * deprecated for good reasons, and a docs page should not teach them.
 */
final class OAuth
{
    /**
     * @param  array<array-key, mixed>  $scheme
     * @return array<string, array{authorizationUrl?: string, tokenUrl: string, scopes: array<string, string>}>
     */
    public static function flows(array $scheme, ?string $base): array
    {
        $flows = is_array($scheme['flows'] ?? null) ? $scheme['flows'] : [];
        $out = [];

        foreach (['authorizationCode', 'clientCredentials'] as $kind) {
            $flow = is_array($flows[$kind] ?? null) ? $flows[$kind] : null;

            if ($flow === null || ! is_string($flow['tokenUrl'] ?? null) || $flow['tokenUrl'] === '') {
                continue;
            }

            if ($kind === 'authorizationCode' && (! is_string($flow['authorizationUrl'] ?? null) || $flow['authorizationUrl'] === '')) {
                continue;
            }

            $scopes = [];

            foreach (is_array($flow['scopes'] ?? null) ? $flow['scopes'] : [] as $scope => $about) {
                $scopes[(string) $scope] = is_string($about) ? $about : '';
            }

            $out[$kind] = array_filter([
                'authorizationUrl' => $kind === 'authorizationCode' ? self::absolute($flow['authorizationUrl'], $base) : null,
                'tokenUrl' => self::absolute($flow['tokenUrl'], $base),
                'scopes' => $scopes,
            ], static fn (mixed $value): bool => $value !== null);
        }

        /** @var array<string, array{authorizationUrl?: string, tokenUrl: string, scopes: array<string, string>}> $out */
        return $out;
    }

    /**
     * Every token address a spec names, for the forwarding route to reach.
     *
     * @return list<string>
     */
    public static function tokenUrls(Spec $spec): array
    {
        $urls = [];

        foreach ($spec->securitySchemes() as $scheme) {
            foreach (($scheme['type'] ?? null) === 'oauth2' ? self::flows($scheme, $spec->serverUrl()) : [] as $flow) {
                $urls[] = $flow['tokenUrl'];
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * A URL as an address the browser can go to. The spec may give one
     * relative to the API's server.
     */
    public static function absolute(string $url, ?string $base): string
    {
        if (preg_match('#^https?://#i', $url) === 1 || $base === null || preg_match('#^https?://#i', $base) !== 1) {
            return $url;
        }

        $origin = (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', $base);

        return str_starts_with($url, '/') ? $origin.$url : rtrim($base, '/').'/'.$url;
    }
}
