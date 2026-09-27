<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Illuminate\Support\Facades\Cache;
use Vellum\Content\ContentRepository;
use Vellum\Search\SearchVisibility;

/**
 * The search index a reader may have, cut to the access levels they hold and
 * cached per set of levels.
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
        $etag = $this->etag($repository, $version, $allowed);
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
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        // A new build changes the ETag, so an old copy is never asked for
        // again. It expires rather than staying in the cache for good.
        Cache::put($cacheKey, $json, now()->addDay());

        return ['json' => $json, 'etag' => $etag];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function etag(ContentRepository $repository, ?string $version, array $allowed): string
    {
        $directory = $this->directory($repository, $version);
        $stamp = (int) @filemtime($directory.'/answers/'.AnswerIndex::FILE);

        return 'answers-'.substr(hash('xxh128', $directory.'|'.$stamp.'|'.implode('+', $allowed)), 0, 16);
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
