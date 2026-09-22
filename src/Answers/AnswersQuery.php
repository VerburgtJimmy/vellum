<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Illuminate\Support\Facades\Cache;
use Vellum\Content\ContentRepository;
use Vellum\Search\SearchVisibility;
use Vellum\Semantic\SemanticSet;

/**
 * The answer index and the semantic file a reader may have, cut to the access
 * levels they hold and cached per set of levels, the way the search index is.
 */
final class AnswersQuery
{
    public function __construct(private readonly SearchVisibility $visibility = new SearchVisibility) {}

    /**
     * The access levels this reader holds, of the ones the docs use.
     *
     * @return list<string>
     */
    public function allowed(AnswerIndex $index): array
    {
        $levels = [];

        foreach ($index->sections as $record) {
            if (! isset($levels[$record['access']]) && $this->visibility->allowsAccess($record['access'])) {
                $levels[$record['access']] = true;
            }
        }

        $allowed = array_map('strval', array_keys($levels));
        sort($allowed);

        return $allowed;
    }

    public function directory(ContentRepository $repository, ?string $version): string
    {
        return $repository->store()->versionPath($this->version($repository, $version));
    }

    public function index(ContentRepository $repository, ?string $version): ?AnswerIndex
    {
        return AnswerIndex::load($this->directory($repository, $version).'/answers');
    }

    /**
     * @return array{json: string, etag: string}|null
     */
    public function json(ContentRepository $repository, ?string $version): ?array
    {
        $index = $this->index($repository, $version);

        if ($index === null) {
            return null;
        }

        $allowed = $this->allowed($index);
        $etag = $this->etag($repository, $version, $allowed, 'answers');
        $cacheKey = 'vellum:answers:'.$etag;
        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return ['json' => $cached, 'etag' => $etag];
        }

        $visible = $index->forAccess($allowed);
        $json = json_encode(
            [
                'sections' => $visible->sections,
                // The built-in groups and this reader's page aliases, merged:
                // the browser expands a query with exactly what PHP would.
                'synonyms' => $visible->synonymGroups(),
                'threshold' => (float) config('vellum.answers.card_threshold', 0.75),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        Cache::forever($cacheKey, $json);

        return ['json' => $json, 'etag' => $etag];
    }

    /**
     * @return array{bytes: string, etag: string}|null
     */
    public function semantic(ContentRepository $repository, ?string $version): ?array
    {
        $index = $this->index($repository, $version);
        $set = SemanticSet::load($this->directory($repository, $version).'/semantic');

        if ($index === null || $set === null) {
            return null;
        }

        $allowed = $this->allowed($index);
        $etag = $this->etag($repository, $version, $allowed, 'semantic');
        $cacheKey = 'vellum:semantic:'.$etag;
        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return ['bytes' => $cached, 'etag' => $etag];
        }

        $bytes = $set->forGroups($allowed);
        Cache::forever($cacheKey, $bytes);

        return ['bytes' => $bytes, 'etag' => $etag];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function etag(ContentRepository $repository, ?string $version, array $allowed, string $kind): string
    {
        $directory = $this->directory($repository, $version);
        $stamp = max(
            (int) @filemtime($directory.'/answers/'.AnswerIndex::FILE),
            (int) @filemtime($directory.'/semantic/semantic.bin'),
        );

        return $kind.'-'.substr(hash('xxh128', $directory.'|'.$stamp.'|'.implode('+', $allowed)), 0, 16);
    }

    private function version(ContentRepository $repository, ?string $version): ?string
    {
        if (! $repository->versionsEnabled()) {
            return null;
        }

        return $version !== null && in_array($version, $repository->versions(), true)
            ? $version
            : $repository->latestVersion();
    }
}
