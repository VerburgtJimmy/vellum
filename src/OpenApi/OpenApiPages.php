<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Content\Document;
use Vellum\Content\SearchIndexBuilder;
use Vellum\Support\VersionUrl;

/**
 * The OpenAPI half of the content set: resolve a spec, build its pages, and
 * hand ContentRepository the same Documents Markdown produces.
 *
 * Work is done once per instance. A nav rebuild, a search rebuild and a full
 * build all ask for the same pages, and parsing a large spec three times for
 * one request would show.
 *
 * @phpstan-import-type SearchDocument from SearchIndexBuilder
 */
final class OpenApiPages
{
    /** @var list<Document>|null */
    private ?array $documents = null;

    private ?Spec $spec = null;

    private bool $resolved = false;

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly SpecSource $source = new SpecSource,
        private readonly SpecParser $parser = new SpecParser,
        private readonly ?PageBuilder $builder = null,
    ) {}

    /**
     * @return list<Document>
     */
    public function documents(?string $version = null): array
    {
        if ($this->documents !== null) {
            return $this->documents;
        }

        $spec = $this->spec();

        return $this->documents = $spec === null ? [] : $this->builder()->build($spec, $version);
    }

    /**
     * @return list<SearchDocument>
     */
    public function searchEntries(string $routePrefix, ?string $version = null): array
    {
        $spec = $this->spec();

        return $spec === null ? [] : $this->builder()->searchEntries($spec, $routePrefix, $version);
    }

    /**
     * The sidebar folder for the reference pages, or null when there is no spec.
     *
     * @return array<string, mixed>|null
     */
    public function navGroup(string $routePrefix, ?string $version, ?string $defaultVersion): ?array
    {
        $documents = $this->documents($version);

        if ($documents === []) {
            return null;
        }

        $overview = $documents[0];
        $children = [];

        foreach ($documents as $index => $document) {
            $children[] = [
                'type' => 'page',
                'slug' => $document->slug,
                // The overview is the folder's own page; the rest are groups.
                'title' => $index === 0 ? 'Overview' : $document->title,
                'description' => $document->description,
                'icon' => null,
                'href' => VersionUrl::href($routePrefix, $document->slug, $version, $defaultVersion),
                'access' => 'guest',
                'badge' => $index === 0 ? null : count($document->headings),
            ];
        }

        $icon = config('vellum.openapi.icon');

        return [
            'type' => 'folder',
            'title' => $overview->title,
            'icon' => is_string($icon) && $icon !== '' ? $icon : null,
            'defaultOpen' => false,
            'children' => $children,
        ];
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        $this->spec();

        return $this->warnings;
    }

    private function spec(): ?Spec
    {
        if ($this->resolved) {
            return $this->spec;
        }

        $this->resolved = true;

        $path = $this->source->resolve();
        $this->warnings = $this->source->warnings();

        if ($path === null) {
            return null;
        }

        $this->spec = $this->parser->parseFile($path);
        $this->warnings = [...$this->warnings, ...$this->spec->warnings];

        return $this->spec;
    }

    private function builder(): PageBuilder
    {
        return $this->builder ?? new PageBuilder(app('view'));
    }
}
