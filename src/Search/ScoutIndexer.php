<?php

declare(strict_types=1);

namespace Vellum\Search;

use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Markdown\Islands\IslandRenderer;

/**
 * Pushes heading-level records into Laravel Scout.
 */
final class ScoutIndexer
{
    /**
     * @param  list<Document>  $documents
     */
    public function sync(ContentRepository $repository, array $documents): void
    {
        SearchDriver::assertScoutInstalled();

        $records = [];

        foreach ($documents as $document) {
            foreach ($this->recordsFor($repository, $document) as $record) {
                $records[] = $record;
            }
        }

        foreach (array_chunk($records, 100) as $chunk) {
            $models = [];

            foreach ($chunk as $attributes) {
                $model = new SearchableDocument($attributes);
                $model->setAttribute('id', $attributes['id']);
                $model->exists = true;
                $models[] = $model;
            }

            $first = $models[0] ?? null;

            if ($first !== null) {
                $first->searchableUsing()->update(collect($models));
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, ?string $version): array
    {
        SearchDriver::assertScoutInstalled();

        $builder = SearchableDocument::search($query);

        if ($version !== null && $version !== '') {
            $builder->where('version', $version);
        }

        $visibility = new SearchVisibility;
        $hits = [];

        foreach ($this->fallbackHits($builder) as $hit) {
            if (! $visibility->allows($hit)) {
                continue;
            }

            $hits[] = [
                'id' => (string) ($hit['id'] ?? ''),
                'title' => (string) ($hit['title'] ?? ''),
                'description' => (string) ($hit['description'] ?? ''),
                'content' => (string) ($hit['content'] ?? ''),
                'url' => (string) ($hit['url'] ?? ''),
                'headings' => [],
                'access' => (string) ($hit['access'] ?? 'guest'),
            ];
        }

        return $hits;
    }

    /**
     * One record for the page, then one per heading.
     *
     * @return list<array<string, mixed>>
     */
    private function recordsFor(ContentRepository $repository, Document $document): array
    {
        $access = $document->access();
        $version = $document->version;
        $url = $repository->hrefFor($document->slug, $version);
        $idPrefix = ($version ?? '').':'.($document->slug === '' ? 'index' : $document->slug);
        $content = html_entity_decode(
            strip_tags((new IslandRenderer)->inlineSlots($document->html, $document->islands)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $content = trim(preg_replace('/\s+/', ' ', $content) ?? $content);

        $records = [[
            'id' => $idPrefix,
            'title' => $document->title,
            'content' => $content,
            'url' => $url,
            'description' => $document->description ?? '',
            'access' => $access,
            'version' => $version,
        ]];

        foreach ($document->headings as $heading) {
            $records[] = [
                'id' => $idPrefix.'#'.$heading['id'],
                'title' => $heading['text'],
                'content' => $heading['text'],
                'url' => $url.'#'.$heading['id'],
                'description' => $document->title,
                'access' => $access,
                'version' => $version,
            ];
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackHits(object $builder): array
    {
        $hits = [];

        foreach ($builder->take(40)->get() as $model) {
            if ($model instanceof SearchableDocument) {
                $hits[] = $model->toSearchableArray();
            }
        }

        return $hits;
    }
}
