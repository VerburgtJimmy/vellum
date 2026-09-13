<?php

declare(strict_types=1);

namespace Vellum\Search;

use Illuminate\Support\Facades\Cache;
use Vellum\Content\ContentRepository;

/**
 * Serves MiniSearch JSON after dropping pages the current user cannot see.
 */
final class SearchIndexQuery
{
    private const GENERATION = 'vellum:search-generation';

    public function __construct(
        private readonly SearchVisibility $visibility = new SearchVisibility,
    ) {}

    public function clear(): void
    {
        $generation = (int) Cache::get(self::GENERATION, 0);
        Cache::forever(self::GENERATION, $generation + 1);
    }

    /**
     * @return array{json: string, etag: string}
     */
    public function json(ContentRepository $repository, ?string $version, ?string $hash = null): array
    {
        $store = $repository->store();

        if ($hash !== null && $hash !== '') {
            $raw = $this->findHashedIndex($repository, $hash);

            if ($raw === null) {
                abort(404);
            }

            $resolvedHash = $hash;
        } else {
            $version = $this->resolveVersion($repository, $version);
            $repository->navigation($version);
            $resolvedHash = $repository->searchHash($version);
            $raw = $store->getSearchIndex($version, $resolvedHash);

            if ($raw === null) {
                abort(404);
            }
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! isset($payload['documents']) || ! is_array($payload['documents'])) {
            abort(404);
        }

        /** @var list<array<string, mixed>> $documents */
        $documents = [];

        foreach ($payload['documents'] as $document) {
            if (is_array($document)) {
                $documents[] = $document;
            }
        }

        $key = $this->visibility->key($documents);
        $etag = ($resolvedHash ?? '').'-'.$key;
        $cacheKey = 'vellum:search:'.$this->generation().':'.$etag;
        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return ['json' => $cached, 'etag' => $etag];
        }

        $filtered = array_values(array_filter(
            $documents,
            fn (array $document): bool => $this->visibility->allows($document),
        ));

        $json = json_encode(
            ['driver' => 'minisearch', 'documents' => $filtered],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        Cache::forever($cacheKey, $json);

        return ['json' => $json, 'etag' => $etag];
    }

    /**
     * Drop pages the current user cannot see (export runs as guest).
     */
    public function filterJson(string $raw): string
    {
        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! isset($payload['documents']) || ! is_array($payload['documents'])) {
            return $raw;
        }

        $documents = [];

        foreach ($payload['documents'] as $document) {
            if (is_array($document) && $this->visibility->allows($document)) {
                $documents[] = $document;
            }
        }

        return json_encode(
            ['driver' => 'minisearch', 'documents' => $documents],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function resolveVersion(ContentRepository $repository, ?string $version): ?string
    {
        if (! $repository->versionsEnabled()) {
            return null;
        }

        if (is_string($version) && $version !== '' && in_array($version, $repository->versions(), true)) {
            return $version;
        }

        return $repository->latestVersion();
    }

    private function findHashedIndex(ContentRepository $repository, string $hash): ?string
    {
        $store = $repository->store();
        $candidates = $repository->versionsEnabled()
            ? $repository->versions()
            : [null];

        if ($repository->versionsEnabled()) {
            $candidates[] = null;
        }

        foreach ($candidates as $candidate) {
            $json = $store->getSearchIndex($candidate, $hash);

            if ($json !== null) {
                return $json;
            }
        }

        return null;
    }

    private function generation(): int
    {
        return (int) Cache::get(self::GENERATION, 0);
    }
}
