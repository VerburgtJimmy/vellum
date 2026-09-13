<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Markdown\Islands\IslandRenderer;
use Vellum\Support\VersionUrl;

/**
 * Builds the client-side MiniSearch document list from compiled pages.
 *
 * @phpstan-type SearchDocument array{
 *     id: string,
 *     title: string,
 *     description: string,
 *     content: string,
 *     url: string,
 *     headings: list<string>,
 *     access: string
 * }
 */
final class SearchIndexBuilder
{
    public function __construct(
        private readonly string $routePrefix = 'docs',
        private readonly ?string $defaultVersion = null,
    ) {}

    /**
     * @param  list<Document>  $documents
     * @return array{documents: list<SearchDocument>, hash: string}
     */
    public function build(array $documents, ?string $version = null): array
    {
        $entries = [];

        foreach ($documents as $document) {
            $access = $document->access();

            $entries[] = [
                'id' => $document->slug === '' ? 'index' : $document->slug,
                'title' => $document->title,
                'description' => $document->description ?? '',
                'content' => $this->plainText((new IslandRenderer)->inlineSlots($document->html, $document->islands)),
                'url' => $this->urlFor($document->slug, $version ?? $document->version),
                'headings' => array_map(
                    static fn (array $heading): string => $heading['text'],
                    $document->headings,
                ),
                'access' => $access,
            ];
        }

        $payload = ['documents' => $entries];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('xxh128', $json);

        return [
            'documents' => $entries,
            'hash' => $hash,
        ];
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return trim($text);
    }

    private function urlFor(string $slug, ?string $version): string
    {
        return VersionUrl::href($this->routePrefix, $slug, $version, $this->defaultVersion);
    }
}
