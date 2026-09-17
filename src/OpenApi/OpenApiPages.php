<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Content\Document;

/**
 * The OpenAPI half of the content set: resolve a spec, build its pages, and
 * hand ContentRepository the same Documents Markdown produces.
 *
 * Work is done once per instance. A nav rebuild, a search rebuild and a full
 * build all ask for the same pages, and parsing a large spec three times for
 * one request would show.
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
     * The sidebar tree for the reference pages.
     *
     * A folder per tag, each holding its operations with the method as a
     * badge, which is how a reader scans a reference: by verb and path, not
     * by prose title alone.
     *
     * @return list<array<string, mixed>>
     */
    public function navTree(?string $version = null): array
    {
        $spec = $this->spec();

        if ($spec === null) {
            return [];
        }

        $prefix = Mount::slugPrefix();
        $groups = (new Grouper)->group($spec, config('vellum.openapi.group_by') === 'path' ? 'path' : 'tag');
        $overview = $this->documents($version)[0] ?? null;
        $tree = [];

        if ($overview !== null) {
            $tree[] = $this->pageNode($overview->slug, 'Overview', $overview->description, $version, null);
        }

        foreach ($groups as $group) {
            $children = [];

            foreach ($group->operations as $operation) {
                $children[] = $this->pageNode(
                    $prefix.'/'.$group->slug.'/'.$group->slugFor($operation),
                    $operation->title(),
                    $operation->summary,
                    $version,
                    $operation->method,
                );
            }

            $tree[] = [
                'type' => 'folder',
                'title' => $group->name,
                'icon' => null,
                'defaultOpen' => false,
                'children' => $children,
            ];
        }

        return $tree;
    }

    /**
     * The whole reference as one collapsible group, for the docs sidebar.
     *
     * @return array<string, mixed>|null
     */
    public function navGroup(?string $version = null): ?array
    {
        $tree = $this->navTree($version);

        if ($tree === []) {
            return null;
        }

        $icon = config('vellum.openapi.icon');
        $title = config('vellum.openapi.title');

        return [
            'type' => 'folder',
            'title' => is_string($title) && $title !== '' ? $title : 'API reference',
            'icon' => is_string($icon) && $icon !== '' ? $icon : null,
            'defaultOpen' => false,
            'children' => $tree,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageNode(string $slug, string $title, ?string $description, ?string $version, ?string $method): array
    {
        return [
            'type' => 'page',
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'icon' => null,
            'href' => Mount::href($slug, $version),
            'access' => 'guest',
            'badge' => $method,
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
